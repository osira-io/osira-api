<?php

declare(strict_types=1);

namespace App\Service\Metrics;

use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Node\Node;

final class ItemDefinitionMetricQueryFactory
{
    public const string METRIC_NAME = 'osira_item_value';

    public function buildQuery(ItemDefinition $itemDefinition, Node $node, MetricLabelFilters $filters): string
    {
        $filterLabels = $filters->toArray();
        $selectors = ['node_id' => (string) $node->id(), 'item_key' => $itemDefinition->key(), ...$filterLabels];
        $parts = [];
        foreach ($selectors as $label => $value) {
            $parts[] = \sprintf('%s="%s"', $label, self::escapeLabelValue($value));
        }

        return \sprintf('%s{%s}', self::METRIC_NAME, implode(',', $parts));
    }

    /** @param array<string, string> $labels
     * @return array<string, string>
     */
    public function dimensionLabels(ItemDefinition $itemDefinition, array $labels): array
    {
        unset($labels['__name__'], $labels['node_id'], $labels['item_key'], $labels['job'], $labels['instance']);
        $dimensions = $labels;
        ksort($dimensions);

        return $dimensions;
    }

    private static function escapeLabelValue(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }
}
