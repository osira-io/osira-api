<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Metrics;

use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\ItemValueType;
use App\Entity\Node\Node;
use App\Service\Metrics\ItemDefinitionMetricQueryFactory;
use App\Service\Metrics\MetricLabelFilters;
use PHPUnit\Framework\TestCase;

final class ItemDefinitionMetricQueryFactoryTest extends TestCase
{
    public function testAnyValidCustomKeyUsesTheGenericMetricContract(): void
    {
        $now = new \DateTimeImmutable();
        $node = new Node('custom-node', null, 'linux', 'x86_64', $now, $now);
        $item = new ItemDefinition('custom.nginx.connections', 'Connections', null, null, ItemValueType::INTEGER, 30, 5, 'printf 42', null, true, $now);

        $query = (new ItemDefinitionMetricQueryFactory())->buildQuery($item, $node, new MetricLabelFilters(device: '/data'));

        self::assertSame('osira_item_value{node_id="'.$node->id().'",item_key="custom.nginx.connections",device="/data"}', $query);
    }

    public function testBuildsNodeAndItemScopedQuery(): void
    {
        $now = new \DateTimeImmutable('2026-08-19T12:00:00+00:00');
        $node = new Node('srv-prod-01', null, 'linux', 'x86_64', $now, $now);
        $item = new ItemDefinition('custom.cpu.usage', 'CPU usage', null, null, ItemValueType::FLOAT, 60, 5, 'printf 1', null, true, $now);

        $query = (new ItemDefinitionMetricQueryFactory())->buildQuery($item, $node, new MetricLabelFilters());

        self::assertSame(\sprintf('osira_item_value{node_id="%s",item_key="custom.cpu.usage"}', $node->id()), $query);
    }

    public function testBuildsQueryWithSupportedLabelFilters(): void
    {
        $now = new \DateTimeImmutable('2026-08-19T12:00:00+00:00');
        $node = new Node('srv-prod-01', null, 'linux', 'x86_64', $now, $now);
        $item = new ItemDefinition('custom.disk.usage', 'Disk usage', null, null, ItemValueType::FLOAT, 60, 5, 'printf 1', null, true, $now);

        $query = (new ItemDefinitionMetricQueryFactory())->buildQuery(
            $item,
            $node,
            new MetricLabelFilters(device: 'nvme0n1p1'),
        );

        self::assertSame(\sprintf('osira_item_value{node_id="%s",item_key="custom.disk.usage",device="nvme0n1p1"}', $node->id()), $query);
    }

    public function testEscapesControlledLabelValuesWithoutAcceptingMetricsQl(): void
    {
        $now = new \DateTimeImmutable('2026-08-19T12:00:00+00:00');
        $node = new Node('srv-prod-01', null, 'linux', 'x86_64', $now, $now);
        $item = new ItemDefinition('custom.cpu.usage', 'CPU usage', null, null, ItemValueType::FLOAT, 60, 5, 'printf 1', null, true, $now);

        $query = (new ItemDefinitionMetricQueryFactory())->buildQuery(
            $item,
            $node,
            new MetricLabelFilters(interface: 'eth0"} or up{'),
        );

        self::assertStringContainsString('interface="eth0\\"} or up{"', $query);
    }

    public function testKeepsAllNonReservedDimensionLabelsForIncidentIdentity(): void
    {
        $now = new \DateTimeImmutable('2026-08-19T12:00:00+00:00');
        $item = new ItemDefinition('custom.network.rx', 'Network RX', null, null, ItemValueType::INTEGER, 60, 5, 'printf 1', null, true, $now);

        self::assertSame(
            ['container' => 'api', 'interface' => 'eth0'],
            (new ItemDefinitionMetricQueryFactory())->dimensionLabels($item, [
                '__name__' => 'osira_container_network_rx',
                'node_id' => 'ignored',
                'interface' => 'eth0',
                'job' => 'stable-dimension',
                'container' => 'api',
                'item_key' => 'custom.network.rx',
            ]),
        );
    }
}
