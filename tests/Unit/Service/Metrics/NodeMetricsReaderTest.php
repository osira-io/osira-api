<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Metrics;

use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\ItemValueType;
use App\Entity\Monitoring\MonitoringTemplate;
use App\Entity\Node\Node;
use App\Service\Metrics\NodeMetricsReader;
use App\Service\Metrics\VictoriaMetricsClientProxy;
use App\Service\Monitoring\MonitoringCatalogSynchronizer;
use App\Tests\Functional\Support\FakeVictoriaMetricsClient;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class NodeMetricsReaderTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private NodeMetricsReader $reader;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->reader = self::getContainer()->get(NodeMetricsReader::class);

        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());
        self::getContainer()->get(MonitoringCatalogSynchronizer::class)->synchronize();
    }

    protected function tearDown(): void
    {
        self::getContainer()->get(VictoriaMetricsClientProxy::class)->reset();
        self::ensureKernelShutdown();

        parent::tearDown();
    }

    public function testQueryInstantNormalizesPublicLabels(): void
    {
        $node = $this->createNodeWithTemplate('srv-prod-01', 'linux-base');
        $fakeClient = new FakeVictoriaMetricsClient();
        $fakeClient->instantResult = [
            FakeVictoriaMetricsClient::sample([
                '__name__' => 'osira_system_cpu_usage',
                'node_id' => (string) $node->id(),
                'interface' => 'eth0',
                'device' => 'cpu0',
            ], '42.5'),
        ];
        $this->useVictoriaMetricsClient($fakeClient);

        $result = $this->reader->queryInstant((string) $node->id(), 'system.cpu.usage', new \App\Service\Metrics\MetricLabelFilters());

        self::assertSame((string) $node->id(), $result->nodeId);
        self::assertSame('system.cpu.usage', $result->itemKey);
        self::assertSame(['device' => 'cpu0', 'interface' => 'eth0'], $result->samples[0]->labels);
        self::assertSame('42.5', $result->samples[0]->value);
    }

    public function testQueryRangeNormalizesSeries(): void
    {
        $node = $this->createNodeWithTemplate('srv-prod-02', 'linux-base');
        $fakeClient = new FakeVictoriaMetricsClient();
        $fakeClient->rangeResult = [
            FakeVictoriaMetricsClient::series([
                '__name__' => 'osira_system_disk_usage',
                'node_id' => (string) $node->id(),
                'device' => 'nvme0n1p1',
            ], [
                ['2026-08-19T12:00:00+00:00', '77.1'],
                ['2026-08-19T12:01:00+00:00', '77.4'],
            ]),
        ];
        $this->useVictoriaMetricsClient($fakeClient);

        $result = $this->reader->queryRange(
            (string) $node->id(),
            'system.disk.usage',
            new \DateTimeImmutable('2026-08-19T12:00:00+00:00'),
            new \DateTimeImmutable('2026-08-19T12:02:00+00:00'),
            60,
            new \App\Service\Metrics\MetricLabelFilters(device: 'nvme0n1p1'),
        );

        self::assertSame((string) $node->id(), $result->nodeId);
        self::assertSame('system.disk.usage', $result->itemKey);
        self::assertSame(['device' => 'nvme0n1p1'], $result->series[0]->labels);
        self::assertCount(2, $result->series[0]->points);
    }

    public function testQueryNodeSnapshotAggregatesEffectiveItems(): void
    {
        $node = $this->createNodeWithTemplate('srv-prod-03', 'linux-base');
        $fakeClient = new FakeVictoriaMetricsClient();
        $fakeClient->instantResult = [FakeVictoriaMetricsClient::sample([
            '__name__' => 'ignored',
            'node_id' => (string) $node->id(),
        ], '1')];
        $this->useVictoriaMetricsClient($fakeClient);

        $result = $this->reader->queryNodeSnapshot((string) $node->id());

        self::assertSame((string) $node->id(), $result->nodeId);
        self::assertCount(9, $result->samples);
        self::assertCount(9, $fakeClient->queries);
    }

    public function testRejectsInvalidNodeId(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->reader->queryInstant('not-a-ulid', 'system.cpu.usage', new \App\Service\Metrics\MetricLabelFilters());
    }

    public function testRejectsUnknownItemDefinitionKey(): void
    {
        $node = $this->createNodeWithTemplate('srv-prod-04', 'linux-base');

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->reader->queryInstant((string) $node->id(), 'unknown.metric', new \App\Service\Metrics\MetricLabelFilters());
    }

    public function testRejectsDisabledItemDefinition(): void
    {
        $node = $this->createNodeWithTemplate('srv-prod-05', 'linux-base');
        $this->attachDisabledTemplate($node, 'custom.disabled.metric');

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->reader->queryInstant((string) $node->id(), 'custom.disabled.metric', new \App\Service\Metrics\MetricLabelFilters());
    }

    public function testRejectsItemThatIsNotAssignedToNode(): void
    {
        $node = $this->createNodeWithTemplate('srv-prod-06', 'linux-base');
        $orphan = new ItemDefinition('custom.orphan.metric', 'Orphan', null, null, null, ItemValueType::FLOAT, 60, null, false, true, new \DateTimeImmutable('2026-08-19T12:00:00+00:00'));
        $this->entityManager->persist($orphan);
        $this->entityManager->flush();

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->reader->queryInstant((string) $node->id(), 'custom.orphan.metric', new \App\Service\Metrics\MetricLabelFilters());
    }

    public function testRejectsRangeWhenFromIsAfterTo(): void
    {
        $node = $this->createNodeWithTemplate('srv-prod-07', 'linux-base');

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->reader->queryRange(
            (string) $node->id(),
            'system.cpu.usage',
            new \DateTimeImmutable('2026-08-19T12:02:00+00:00'),
            new \DateTimeImmutable('2026-08-19T12:00:00+00:00'),
            60,
            new \App\Service\Metrics\MetricLabelFilters(),
        );
    }

    private function createNodeWithTemplate(string $hostname, string $templateSlug): Node
    {
        $now = new \DateTimeImmutable('2026-08-19T12:00:00+00:00');
        $node = new Node($hostname, null, 'linux', 'x86_64', $now, $now);
        $template = $this->entityManager->getRepository(MonitoringTemplate::class)->findOneBy(['slug' => $templateSlug]);
        \assert($template instanceof MonitoringTemplate);
        $node->replaceMonitoringTemplates([$template]);
        $this->entityManager->persist($node);
        $this->entityManager->flush();

        return $node;
    }

    private function attachDisabledTemplate(Node $node, string $itemKey): void
    {
        $now = new \DateTimeImmutable('2026-08-19T12:00:00+00:00');
        $item = new ItemDefinition($itemKey, 'Disabled', null, null, null, ItemValueType::FLOAT, 60, null, false, false, $now);
        $template = new MonitoringTemplate('Disabled Template', 'disabled-template', null, false, true, $now);
        $template->replaceItemDefinitions([$item], $now);

        $linuxBase = $this->entityManager->getRepository(MonitoringTemplate::class)->findOneBy(['slug' => 'linux-base']);
        \assert($linuxBase instanceof MonitoringTemplate);

        $this->entityManager->persist($item);
        $this->entityManager->persist($template);
        $node->replaceMonitoringTemplates([$linuxBase, $template]);
        $this->entityManager->flush();
    }

    private function useVictoriaMetricsClient(FakeVictoriaMetricsClient $client): void
    {
        self::getContainer()->get(VictoriaMetricsClientProxy::class)->useClient($client);
    }
}
