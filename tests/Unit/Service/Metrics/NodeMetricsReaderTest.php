<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Metrics;

use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\ItemValueType;
use App\Entity\Monitoring\MonitoringTemplate;
use App\Entity\Node\Node;
use App\Entity\NodeGroup\NodeGroup;
use App\Service\Metrics\NodeMetricsReader;
use App\Service\Metrics\VictoriaMetricsClientProxy;
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
    }

    protected function tearDown(): void
    {
        self::getContainer()->get(VictoriaMetricsClientProxy::class)->reset();
        self::ensureKernelShutdown();

        parent::tearDown();
    }

    public function testQueryInstantNormalizesPublicLabels(): void
    {
        $node = $this->createNodeWithTemplate('srv-prod-01');
        $fakeClient = new FakeVictoriaMetricsClient();
        $fakeClient->instantResult = [
            FakeVictoriaMetricsClient::sample([
                '__name__' => 'osira_item_value',
                'node_id' => (string) $node->id(),
                'item_key' => 'custom.cpu.usage',
                'interface' => 'eth0',
                'device' => 'cpu0',
            ], '42.5'),
        ];
        $this->useVictoriaMetricsClient($fakeClient);

        $result = $this->reader->queryInstant((string) $node->id(), 'custom.cpu.usage', new \App\Service\Metrics\MetricLabelFilters());

        self::assertSame((string) $node->id(), $result->nodeId);
        self::assertSame('custom.cpu.usage', $result->itemKey);
        self::assertSame(['device' => 'cpu0', 'interface' => 'eth0'], $result->samples[0]->labels);
        self::assertSame('42.5', $result->samples[0]->value);
    }

    public function testQueryRangeNormalizesSeries(): void
    {
        $node = $this->createNodeWithTemplate('srv-prod-02');
        $fakeClient = new FakeVictoriaMetricsClient();
        $fakeClient->rangeResult = [
            FakeVictoriaMetricsClient::series([
                '__name__' => 'osira_item_value',
                'node_id' => (string) $node->id(),
                'item_key' => 'custom.disk.usage',
                'device' => 'nvme0n1p1',
            ], [
                ['2026-08-19T12:00:00+00:00', '77.1'],
                ['2026-08-19T12:01:00+00:00', '77.4'],
            ]),
        ];
        $this->useVictoriaMetricsClient($fakeClient);

        $result = $this->reader->queryRange(
            (string) $node->id(),
            'custom.disk.usage',
            new \DateTimeImmutable('2026-08-19T12:00:00+00:00'),
            new \DateTimeImmutable('2026-08-19T12:02:00+00:00'),
            60,
            new \App\Service\Metrics\MetricLabelFilters(device: 'nvme0n1p1'),
        );

        self::assertSame((string) $node->id(), $result->nodeId);
        self::assertSame('custom.disk.usage', $result->itemKey);
        self::assertSame(['device' => 'nvme0n1p1'], $result->series[0]->labels);
        self::assertCount(2, $result->series[0]->points);
    }

    public function testQueryNodeSnapshotAggregatesEffectiveItems(): void
    {
        $node = $this->createNodeWithTemplate('srv-prod-03');
        $fakeClient = new FakeVictoriaMetricsClient();
        $fakeClient->instantResult = [FakeVictoriaMetricsClient::sample([
            '__name__' => 'ignored',
            'node_id' => (string) $node->id(),
        ], '1')];
        $this->useVictoriaMetricsClient($fakeClient);

        $result = $this->reader->queryNodeSnapshot((string) $node->id());

        self::assertSame((string) $node->id(), $result->nodeId);
        self::assertCount(2, $result->samples);
        self::assertCount(2, $fakeClient->queries);
    }

    public function testRejectsInvalidNodeId(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->reader->queryInstant('not-a-ulid', 'custom.cpu.usage', new \App\Service\Metrics\MetricLabelFilters());
    }

    public function testRejectsUnknownItemDefinitionKey(): void
    {
        $node = $this->createNodeWithTemplate('srv-prod-04');

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->reader->queryInstant((string) $node->id(), 'unknown.metric', new \App\Service\Metrics\MetricLabelFilters());
    }

    public function testRejectsDisabledItemDefinition(): void
    {
        $node = $this->createNodeWithTemplate('srv-prod-05');
        $this->attachDisabledTemplate($node, 'custom.disabled.metric');

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->reader->queryInstant((string) $node->id(), 'custom.disabled.metric', new \App\Service\Metrics\MetricLabelFilters());
    }

    public function testRejectsItemThatIsNotAssignedToNode(): void
    {
        $node = $this->createNodeWithTemplate('srv-prod-06');
        $orphan = new ItemDefinition('custom.orphan.metric', 'Orphan', null, null, ItemValueType::FLOAT, 60, 5, 'printf 1', null, true, new \DateTimeImmutable('2026-08-19T12:00:00+00:00'));
        $this->entityManager->persist($orphan);
        $this->entityManager->flush();

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->reader->queryInstant((string) $node->id(), 'custom.orphan.metric', new \App\Service\Metrics\MetricLabelFilters());
    }

    public function testRejectsRangeWhenFromIsAfterTo(): void
    {
        $node = $this->createNodeWithTemplate('srv-prod-07');

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->reader->queryRange(
            (string) $node->id(),
            'custom.cpu.usage',
            new \DateTimeImmutable('2026-08-19T12:02:00+00:00'),
            new \DateTimeImmutable('2026-08-19T12:00:00+00:00'),
            60,
            new \App\Service\Metrics\MetricLabelFilters(),
        );
    }

    private function createNodeWithTemplate(string $hostname): Node
    {
        $now = new \DateTimeImmutable('2026-08-19T12:00:00+00:00');
        $node = new Node($hostname, null, 'linux', 'x86_64', $now, $now);
        $cpu = new ItemDefinition('custom.cpu.usage', 'CPU', null, '%', ItemValueType::FLOAT, 60, 5, 'printf 1', null, true, $now);
        $disk = new ItemDefinition('custom.disk.usage', 'Disk', null, '%', ItemValueType::FLOAT, 60, 5, 'printf 1', null, true, $now);
        $template = new MonitoringTemplate('Metrics '.$hostname, 'metrics-'.$hostname, null, true, $now);
        $template->replaceItemDefinitions([$cpu, $disk], $now);
        $group = new NodeGroup('Metrics '.$hostname, null, $now);
        $group->replaceMonitoringTemplates([$template], $now);
        $node->replaceGroups([$group]);
        foreach ([$cpu, $disk, $template, $group, $node] as $entity) {
            $this->entityManager->persist($entity);
        }
        $this->entityManager->flush();

        return $node;
    }

    private function attachDisabledTemplate(Node $node, string $itemKey): void
    {
        $now = new \DateTimeImmutable('2026-08-19T12:00:00+00:00');
        $item = new ItemDefinition($itemKey, 'Disabled', null, null, ItemValueType::FLOAT, 60, 5, 'printf 1', null, false, $now);
        $template = new MonitoringTemplate('Disabled Template', 'disabled-template', null, true, $now);
        $template->replaceItemDefinitions([$item], $now);
        $group = new NodeGroup('Disabled group', null, $now);
        $group->replaceMonitoringTemplates([$template], $now);

        $this->entityManager->persist($item);
        $this->entityManager->persist($template);
        $this->entityManager->persist($group);
        $node->replaceGroups([...$node->groups()->toArray(), $group]);
        $this->entityManager->flush();
    }

    private function useVictoriaMetricsClient(FakeVictoriaMetricsClient $client): void
    {
        self::getContainer()->get(VictoriaMetricsClientProxy::class)->useClient($client);
    }
}
