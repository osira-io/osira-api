<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Metrics;

use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\ItemValueType;
use App\Entity\Node\Node;
use App\Service\Metrics\ItemDefinitionMetricQueryFactory;
use App\Service\Metrics\MetricLabelFilters;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class ItemDefinitionMetricQueryFactoryTest extends TestCase
{
    public function testBuildsNodeScopedQueryForSystemCpuUsage(): void
    {
        $now = new \DateTimeImmutable('2026-08-19T12:00:00+00:00');
        $node = new Node('srv-prod-01', null, 'linux', 'x86_64', $now, $now);
        $item = new ItemDefinition('system.cpu.usage', 'CPU usage', null, null, null, ItemValueType::FLOAT, 60, null, true, true, $now);

        $query = (new ItemDefinitionMetricQueryFactory())->buildQuery($item, $node, new MetricLabelFilters());

        self::assertSame(\sprintf('osira_system_cpu_usage{node_id="%s"}', $node->id()), $query);
    }

    public function testBuildsQueryWithSupportedLabelFilters(): void
    {
        $now = new \DateTimeImmutable('2026-08-19T12:00:00+00:00');
        $node = new Node('srv-prod-01', null, 'linux', 'x86_64', $now, $now);
        $item = new ItemDefinition('system.disk.usage', 'Disk usage', null, null, null, ItemValueType::FLOAT, 60, null, true, true, $now);

        $query = (new ItemDefinitionMetricQueryFactory())->buildQuery(
            $item,
            $node,
            new MetricLabelFilters(device: 'nvme0n1p1'),
        );

        self::assertSame(\sprintf('osira_system_disk_usage{node_id="%s",device="nvme0n1p1"}', $node->id()), $query);
    }

    public function testRejectsUnsupportedLabelFilters(): void
    {
        $now = new \DateTimeImmutable('2026-08-19T12:00:00+00:00');
        $node = new Node('srv-prod-01', null, 'linux', 'x86_64', $now, $now);
        $item = new ItemDefinition('system.cpu.usage', 'CPU usage', null, null, null, ItemValueType::FLOAT, 60, null, true, true, $now);

        $this->expectException(UnprocessableEntityHttpException::class);
        (new ItemDefinitionMetricQueryFactory())->buildQuery(
            $item,
            $node,
            new MetricLabelFilters(interface: 'eth0'),
        );
    }

    public function testKeepsOnlyMappedDimensionLabelsForIncidentIdentity(): void
    {
        $now = new \DateTimeImmutable('2026-08-19T12:00:00+00:00');
        $item = new ItemDefinition('container.network.rx', 'Network RX', null, null, null, ItemValueType::INTEGER, 60, null, true, true, $now);

        self::assertSame(
            ['container' => 'api', 'interface' => 'eth0'],
            (new ItemDefinitionMetricQueryFactory())->dimensionLabels($item, [
                '__name__' => 'osira_container_network_rx',
                'node_id' => 'ignored',
                'interface' => 'eth0',
                'job' => 'volatile',
                'container' => 'api',
            ]),
        );
    }
}
