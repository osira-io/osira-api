<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service\Alert;

use App\Entity\Alert\AlertOperator;
use App\Entity\Alert\AlertRule;
use App\Entity\Alert\AlertRuleImpactType;
use App\Entity\Alert\AlertSeverity;
use App\Entity\Incident\Incident;
use App\Entity\Incident\IncidentStatus;
use App\Entity\Maintenance\MaintenanceWindow;
use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\ItemValueType;
use App\Entity\Monitoring\MonitoringTemplate;
use App\Entity\Node\Node;
use App\Entity\NodeGroup\NodeGroup;
use App\Repository\Incident\IncidentRepository;
use App\Service\Alert\AlertEvaluationService;
use App\Service\Metrics\VictoriaMetricsClientProxy;
use App\Service\Metrics\VictoriaMetricsUnavailableException;
use App\Tests\Functional\Support\FakeVictoriaMetricsClient;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AlertEvaluationServiceTest extends KernelTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    protected function setUp(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $schema = new SchemaTool($em);
        $schema->dropDatabase();
        $schema->createSchema($em->getMetadataFactory()->getAllMetadata());
    }

    protected function tearDown(): void
    {
        self::getContainer()->get(VictoriaMetricsClientProxy::class)->reset();
        parent::tearDown();
    }

    public function testOpensUpdatesAndResolvesWithoutRecoveringOnBackendError(): void
    {
        $now = new \DateTimeImmutable();
        $node = new Node('evaluation-node', null, 'linux', 'amd64', $now, $now);
        $item = new ItemDefinition('custom.cpu.usage', 'CPU', null, '%', ItemValueType::FLOAT, 60, 5, 'printf 95', null, true, $now);
        $template = new MonitoringTemplate('Evaluation', 'evaluation', null, true, $now);
        $template->replaceItemDefinitions([$item], $now);
        $group = new NodeGroup('Evaluation group', null, $now);
        $group->replaceMonitoringTemplates([$template], $now);
        $node->replaceGroups([$group]);
        $rule = new AlertRule('CPU high', 'CPU usage is high', $item, AlertOperator::GT, '90', '80', 300, 3, AlertSeverity::CRITICAL, AlertRuleImpactType::AVAILABILITY, true, $now);
        $rule->assignToTemplate($template);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        foreach ([$node, $group, $item, $template, $rule] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        $fake = new FakeVictoriaMetricsClient();
        $fake->rangeResult = [FakeVictoriaMetricsClient::series(['node_id' => (string) $node->id()], [
            [$now->modify('-120 seconds')->format(\DATE_ATOM), '91'],
            [$now->modify('-60 seconds')->format(\DATE_ATOM), '92'],
            [$now->format(\DATE_ATOM), '93'],
        ])];
        self::getContainer()->get(VictoriaMetricsClientProxy::class)->useClient($fake);
        $report = self::getContainer()->get(AlertEvaluationService::class)->evaluateAll();
        self::assertSame(1, $report->firing);
        $incident = self::getContainer()->get(IncidentRepository::class)->findOneBy([]);
        self::assertInstanceOf(Incident::class, $incident);
        self::assertSame(IncidentStatus::FIRING, $incident->status());
        self::assertGreaterThanOrEqual(1, $this->auditCount());

        self::getContainer()->get(AlertEvaluationService::class)->evaluateAll();
        self::assertCount(1, self::getContainer()->get(IncidentRepository::class)->findAll());
        self::assertSame(2, $incident->occurrences());

        $fake->rangeException = new VictoriaMetricsUnavailableException('down');
        $report = self::getContainer()->get(AlertEvaluationService::class)->evaluateAll();
        self::assertSame(1, $report->errors);
        self::assertSame(IncidentStatus::FIRING, $incident->status());

        $fake->rangeException = null;
        $fake->rangeResult = [];
        $report = self::getContainer()->get(AlertEvaluationService::class)->evaluateAll();
        self::assertSame(1, $report->noData);
        self::assertSame(IncidentStatus::FIRING, $incident->status());

        $later = new \DateTimeImmutable();
        $fake->rangeResult = [FakeVictoriaMetricsClient::series(['node_id' => (string) $node->id()], [[$later->format(\DATE_ATOM), '79']])];
        self::getContainer()->get(AlertEvaluationService::class)->evaluateAll();
        self::assertSame(IncidentStatus::RESOLVED, $incident->status());
        self::assertGreaterThanOrEqual(2, $this->auditCount());

        $fake->rangeResult = [FakeVictoriaMetricsClient::series(['node_id' => (string) $node->id()], [
            [$later->modify('-2 seconds')->format(\DATE_ATOM), '91'],
            [$later->modify('-1 second')->format(\DATE_ATOM), '92'],
            [$later->format(\DATE_ATOM), '93'],
        ])];
        self::getContainer()->get(AlertEvaluationService::class)->evaluateAll();
        self::assertCount(2, self::getContainer()->get(IncidentRepository::class)->findAll());
    }

    public function testCreatesOneIncidentPerMappedMetricDimension(): void
    {
        $now = new \DateTimeImmutable();
        $node = new Node('dimension-node', null, 'linux', 'amd64', $now, $now);
        $item = new ItemDefinition('custom.disk.usage', 'Disk', null, '%', ItemValueType::FLOAT, 60, 5, 'printf 95', null, true, $now);
        $template = new MonitoringTemplate('Disk evaluation', 'disk-evaluation', null, true, $now);
        $template->replaceItemDefinitions([$item], $now);
        $group = new NodeGroup('Disk evaluation group', null, $now);
        $group->replaceMonitoringTemplates([$template], $now);
        $node->replaceGroups([$group]);
        $rule = new AlertRule('Disk high', 'Disk usage is high', $item, AlertOperator::GT, '90', '80', 300, 1, AlertSeverity::CRITICAL, AlertRuleImpactType::AVAILABILITY, true, $now);
        $rule->assignToTemplate($template);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        foreach ([$node, $group, $item, $template, $rule] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        $fake = new FakeVictoriaMetricsClient();
        $fake->rangeResult = [
            FakeVictoriaMetricsClient::series(['node_id' => (string) $node->id(), 'device' => '/dev/sda1', 'job' => 'ignored'], [[$now->format(\DATE_ATOM), '91']]),
            FakeVictoriaMetricsClient::series(['node_id' => (string) $node->id(), 'device' => '/data', 'job' => 'ignored'], [[$now->format(\DATE_ATOM), '92']]),
        ];
        self::getContainer()->get(VictoriaMetricsClientProxy::class)->useClient($fake);
        self::getContainer()->get(AlertEvaluationService::class)->evaluateAll();

        $incidents = self::getContainer()->get(IncidentRepository::class)->findAll();
        self::assertCount(2, $incidents);
        $labels = array_map(static fn (Incident $incident): array => $incident->labels(), $incidents);
        self::assertContains(['device' => '/dev/sda1'], $labels);
        self::assertContains(['device' => '/data'], $labels);
    }

    public function testDoesNotOpenNewIncidentsWhileNodeIsInMaintenance(): void
    {
        $now = new \DateTimeImmutable();
        [$node] = $this->seedEvaluatedNode($now);
        $maintenance = new MaintenanceWindow('Production maintenance', null, $now->modify('-1 minute'), $now->modify('+1 hour'), true, $now);
        $maintenance->replaceNodes([$node], $now);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($maintenance);
        $em->flush();

        $fake = new FakeVictoriaMetricsClient();
        $fake->rangeResult = [FakeVictoriaMetricsClient::series(['node_id' => (string) $node->id()], [[$now->format(\DATE_ATOM), '95']])];
        self::getContainer()->get(VictoriaMetricsClientProxy::class)->useClient($fake);

        $report = self::getContainer()->get(AlertEvaluationService::class)->evaluateAll();

        self::assertSame(0, $report->firing);
        self::assertSame(0, $report->ok);
        self::assertCount(0, self::getContainer()->get(IncidentRepository::class)->findAll());
    }

    public function testMaintenanceDoesNotResolveExistingFiringIncidentAndEvaluationResumesAfterwards(): void
    {
        $now = new \DateTimeImmutable();
        [$node] = $this->seedEvaluatedNode($now);
        $fake = new FakeVictoriaMetricsClient();
        $fake->rangeResult = [FakeVictoriaMetricsClient::series(['node_id' => (string) $node->id()], [[$now->format(\DATE_ATOM), '95']])];
        self::getContainer()->get(VictoriaMetricsClientProxy::class)->useClient($fake);
        self::getContainer()->get(AlertEvaluationService::class)->evaluateAll();
        $incident = self::getContainer()->get(IncidentRepository::class)->findOneBy([]);
        self::assertInstanceOf(Incident::class, $incident);
        self::assertSame(IncidentStatus::FIRING, $incident->status());

        $maintenance = new MaintenanceWindow('Production maintenance', null, $now->modify('-1 minute'), $now->modify('+1 hour'), true, $now);
        $maintenance->replaceNodes([$node], $now);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($maintenance);
        $em->flush();

        $fake->rangeResult = [FakeVictoriaMetricsClient::series(['node_id' => (string) $node->id()], [[$now->format(\DATE_ATOM), '75']])];
        self::getContainer()->get(AlertEvaluationService::class)->evaluateAll();
        self::assertSame(IncidentStatus::FIRING, $incident->status());

        $fake->rangeResult = [];
        $report = self::getContainer()->get(AlertEvaluationService::class)->evaluateAll();
        self::assertSame(0, $report->noData);
        self::assertSame(IncidentStatus::FIRING, $incident->status());

        $maintenance->update('Production maintenance', null, $now->modify('-2 hours'), $now->modify('-1 hour'), true, $now);
        $em->flush();
        $em->clear();
        $fake->rangeResult = [FakeVictoriaMetricsClient::series(['node_id' => (string) $node->id()], [[$now->format(\DATE_ATOM), '75']])];
        self::getContainer()->get(AlertEvaluationService::class)->evaluateAll();
        $incident = self::getContainer()->get(IncidentRepository::class)->find($incident->id());
        self::assertInstanceOf(Incident::class, $incident);
        self::assertSame(IncidentStatus::RESOLVED, $incident->status());

        $fake->rangeResult = [FakeVictoriaMetricsClient::series(['node_id' => (string) $node->id()], [[$now->format(\DATE_ATOM), '95']])];
        self::getContainer()->get(AlertEvaluationService::class)->evaluateAll();
        self::assertCount(2, self::getContainer()->get(IncidentRepository::class)->findAll());
    }

    private function auditCount(): int
    {
        $count = self::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM audit_incidents');
        if (!\is_int($count) && !\is_string($count)) {
            throw new \LogicException('Unexpected audit count result.');
        }

        return (int) $count;
    }

    /** @return array{Node, AlertRule} */
    private function seedEvaluatedNode(\DateTimeImmutable $now): array
    {
        $node = new Node('maintenance-evaluation-node', null, 'linux', 'amd64', $now, $now);
        $item = new ItemDefinition('custom.maintenance.cpu', 'CPU', null, '%', ItemValueType::FLOAT, 60, 5, 'printf 95', null, true, $now);
        $template = new MonitoringTemplate('Maintenance evaluation', 'maintenance-evaluation', null, true, $now);
        $template->replaceItemDefinitions([$item], $now);
        $group = new NodeGroup('Maintenance evaluation group', null, $now);
        $group->replaceMonitoringTemplates([$template], $now);
        $node->replaceGroups([$group]);
        $rule = new AlertRule('Maintenance CPU high', 'CPU usage is high', $item, AlertOperator::GT, '90', '80', 300, 1, AlertSeverity::CRITICAL, AlertRuleImpactType::AVAILABILITY, true, $now);
        $rule->assignToTemplate($template);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        foreach ([$node, $group, $item, $template, $rule] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        return [$node, $rule];
    }
}
