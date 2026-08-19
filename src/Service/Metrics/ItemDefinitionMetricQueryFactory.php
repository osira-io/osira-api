<?php

declare(strict_types=1);

namespace App\Service\Metrics;

use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Node\Node;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class ItemDefinitionMetricQueryFactory
{
    /** @var array<string, array{metric: string, labels: list<string>}> */
    private const array MAP = [
        'system.cpu.usage' => ['metric' => 'osira_system_cpu_usage', 'labels' => []],
        'system.memory.usage' => ['metric' => 'osira_system_memory_usage', 'labels' => []],
        'system.load.1' => ['metric' => 'osira_system_load_1', 'labels' => []],
        'system.load.5' => ['metric' => 'osira_system_load_5', 'labels' => []],
        'system.load.15' => ['metric' => 'osira_system_load_15', 'labels' => []],
        'system.disk.usage' => ['metric' => 'osira_system_disk_usage', 'labels' => ['device']],
        'system.network.rx' => ['metric' => 'osira_system_network_rx', 'labels' => ['interface']],
        'system.network.tx' => ['metric' => 'osira_system_network_tx', 'labels' => ['interface']],
        'system.uptime' => ['metric' => 'osira_system_uptime_seconds', 'labels' => []],
        'container.cpu.usage' => ['metric' => 'osira_container_cpu_usage', 'labels' => ['container']],
        'container.memory.usage' => ['metric' => 'osira_container_memory_usage', 'labels' => ['container']],
        'container.network.rx' => ['metric' => 'osira_container_network_rx', 'labels' => ['container', 'interface']],
        'container.network.tx' => ['metric' => 'osira_container_network_tx', 'labels' => ['container', 'interface']],
        'container.restart.count' => ['metric' => 'osira_container_restart_count', 'labels' => ['container']],
        'container.state' => ['metric' => 'osira_container_state', 'labels' => ['container']],
    ];

    public function buildQuery(ItemDefinition $itemDefinition, Node $node, MetricLabelFilters $filters): string
    {
        $definition = self::MAP[$itemDefinition->key()] ?? null;
        if (null === $definition) {
            throw new UnprocessableEntityHttpException(\sprintf('No VictoriaMetrics mapping exists for item definition "%s".', $itemDefinition->key()));
        }

        $filterLabels = $filters->toArray();
        $unsupported = array_diff(array_keys($filterLabels), $definition['labels']);
        if ([] !== $unsupported) {
            throw new UnprocessableEntityHttpException(\sprintf('The item definition "%s" does not support label filters: %s.', $itemDefinition->key(), implode(', ', $unsupported)));
        }

        $selectors = ['node_id' => (string) $node->id(), ...$filterLabels];
        $parts = [];
        foreach ($selectors as $label => $value) {
            $parts[] = \sprintf('%s="%s"', $label, self::escapeLabelValue($value));
        }

        return \sprintf('%s{%s}', $definition['metric'], implode(',', $parts));
    }

    private static function escapeLabelValue(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }
}
