<?php

declare(strict_types=1);

namespace App\Service\Metrics;

use App\Dto\Metrics\MetricInstantQueryOutput;
use App\Dto\Metrics\MetricPointOutput;
use App\Dto\Metrics\MetricRangeQueryOutput;
use App\Dto\Metrics\MetricSampleOutput;
use App\Dto\Metrics\MetricSeriesOutput;
use App\Dto\Metrics\NodeMetricsOutput;
use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Node\Node;
use App\Repository\Monitoring\ItemDefinitionRepository;
use App\Repository\Node\NodeRepository;
use App\Service\Monitoring\EffectiveNodeMonitoringResolver;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Ulid;

final readonly class NodeMetricsReader
{
    public function __construct(
        private NodeRepository $nodes,
        private ItemDefinitionRepository $itemDefinitions,
        private EffectiveNodeMonitoringResolver $resolver,
        private ItemDefinitionMetricQueryFactory $queryFactory,
        private VictoriaMetricsClientInterface $victoriaMetrics,
    ) {
    }

    public function queryInstant(string $nodeId, string $itemKey, MetricLabelFilters $filters): MetricInstantQueryOutput
    {
        $node = $this->findNode($nodeId);
        $itemDefinition = $this->resolveItemDefinition($node, $itemKey);
        $samples = $this->victoriaMetrics->instantQuery($this->queryFactory->buildQuery($itemDefinition, $node, $filters));

        return new MetricInstantQueryOutput(
            (string) $node->id(),
            $itemDefinition->key(),
            array_map(fn (VictoriaMetricsSample $sample): MetricSampleOutput => $this->normalizeSample($itemDefinition->key(), $sample), $samples),
        );
    }

    public function queryRange(string $nodeId, string $itemKey, \DateTimeImmutable $from, \DateTimeImmutable $to, int $stepSeconds, MetricLabelFilters $filters): MetricRangeQueryOutput
    {
        if ($from > $to) {
            throw new UnprocessableEntityHttpException('The "from" timestamp must be earlier than or equal to "to".');
        }

        $node = $this->findNode($nodeId);
        $itemDefinition = $this->resolveItemDefinition($node, $itemKey);
        $series = $this->victoriaMetrics->rangeQuery($this->queryFactory->buildQuery($itemDefinition, $node, $filters), $from, $to, $stepSeconds);

        return new MetricRangeQueryOutput(
            (string) $node->id(),
            $itemDefinition->key(),
            array_map(fn (VictoriaMetricsRangeSeries $row): MetricSeriesOutput => $this->normalizeSeries($itemDefinition->key(), $row), $series),
        );
    }

    public function queryNodeSnapshot(string $nodeId): NodeMetricsOutput
    {
        $node = $this->findNode($nodeId);
        $resolved = $this->resolver->resolve($node);
        $samples = [];

        foreach ($resolved->items as $itemDefinition) {
            $query = $this->queryFactory->buildQuery($itemDefinition, $node, new MetricLabelFilters());
            foreach ($this->victoriaMetrics->instantQuery($query) as $sample) {
                $samples[] = $this->normalizeSample($itemDefinition->key(), $sample);
            }
        }

        return new NodeMetricsOutput((string) $node->id(), $samples);
    }

    private function findNode(string $nodeId): Node
    {
        if (!Ulid::isValid($nodeId)) {
            throw new NotFoundHttpException('Node not found.');
        }

        $node = $this->nodes->find(new Ulid($nodeId));
        if (!$node instanceof Node) {
            throw new NotFoundHttpException('Node not found.');
        }

        return $node;
    }

    private function resolveItemDefinition(Node $node, string $itemKey): ItemDefinition
    {
        $itemDefinition = $this->itemDefinitions->findOneBy(['key' => $itemKey]);
        if (!$itemDefinition instanceof ItemDefinition) {
            throw new UnprocessableEntityHttpException(\sprintf('Unknown item definition key "%s".', $itemKey));
        }
        if (!$itemDefinition->isEnabled()) {
            throw new UnprocessableEntityHttpException(\sprintf('The item definition "%s" is disabled.', $itemKey));
        }

        $resolved = $this->resolver->resolve($node);
        foreach ($resolved->items as $effectiveItem) {
            if ($effectiveItem->key() === $itemKey) {
                return $effectiveItem;
            }
        }

        throw new UnprocessableEntityHttpException(\sprintf('The item definition "%s" is not assigned to node "%s".', $itemKey, $node->hostname()));
    }

    private function normalizeSample(string $metricKey, VictoriaMetricsSample $sample): MetricSampleOutput
    {
        return new MetricSampleOutput($metricKey, self::publicLabels($sample->labels), $sample->timestamp, $sample->value);
    }

    private function normalizeSeries(string $metricKey, VictoriaMetricsRangeSeries $series): MetricSeriesOutput
    {
        return new MetricSeriesOutput(
            $metricKey,
            self::publicLabels($series->labels),
            array_map(
                static fn (VictoriaMetricsRangeSeriesPoint $point): MetricPointOutput => new MetricPointOutput($point->timestamp, $point->value),
                $series->points,
            ),
        );
    }

    /** @param array<string, string> $labels
     * @return array<string, string>
     */
    private static function publicLabels(array $labels): array
    {
        unset($labels['__name__'], $labels['node_id'], $labels['item_key'], $labels['job'], $labels['instance']);
        ksort($labels);

        return $labels;
    }
}
