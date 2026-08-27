<?php

declare(strict_types=1);

namespace App\Service\Alert;

use App\Entity\Alert\AlertRule;
use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Node\Node;
use App\Repository\Incident\IncidentRepository;
use App\Service\Incident\IncidentManager;
use App\Service\Maintenance\MaintenanceResolver;
use App\Service\Metrics\ItemDefinitionMetricQueryFactory;
use App\Service\Metrics\MetricLabelFilters;
use App\Service\Metrics\VictoriaMetricsClientInterface;
use App\Service\Metrics\VictoriaMetricsRangeSeries;
use App\Service\Monitoring\EffectiveNodeMonitoringResolver;
use Psr\Log\LoggerInterface;

final readonly class AlertEvaluationManager
{
    public function __construct(
        private EffectiveNodeMonitoringResolver $resolver,
        private ItemDefinitionMetricQueryFactory $queryFactory,
        private VictoriaMetricsClientInterface $victoriaMetrics,
        private AlertRuleEvaluator $evaluator,
        private IncidentRepository $incidents,
        private IncidentManager $incidentManager,
        private MaintenanceResolver $maintenanceResolver,
        private LoggerInterface $logger,
    ) {
    }

    public function evaluateNode(Node $node, \DateTimeImmutable $evaluatedAt): AlertEvaluationReport
    {
        $rules = $this->resolver->getEffectiveAlertRules($node);
        $times = [];
        foreach ($rules as $rule) {
            $times[(string) $rule->itemDefinition()->id()] = ['item' => $rule->itemDefinition(), 'evaluatedAt' => $evaluatedAt];
        }

        return $this->evaluateItems($node, $times, $rules);
    }

    /**
     * @param array<string, array{item: ItemDefinition, evaluatedAt: \DateTimeImmutable}> $items
     * @param list<AlertRule>|null $effectiveRules
     */
    public function evaluateItems(Node $node, array $items, ?array $effectiveRules = null): AlertEvaluationReport
    {
        $counts = ['nodes' => 1, 'rules' => 0, 'series' => 0, 'firing' => 0, 'ok' => 0, 'noData' => 0, 'errors' => 0];
        if ([] === $items || $this->maintenanceResolver->isInMaintenance($node)) {
            return new AlertEvaluationReport(...$counts);
        }

        $rulesByItem = [];
        foreach ($effectiveRules ?? $this->resolver->getEffectiveAlertRules($node) as $rule) {
            $itemId = (string) $rule->itemDefinition()->id();
            if (isset($items[$itemId])) {
                $rulesByItem[$itemId][] = $rule;
            }
        }

        foreach ($rulesByItem as $itemId => $itemRules) {
            $item = $items[$itemId]['item'];
            $evaluatedAt = $items[$itemId]['evaluatedAt'];
            $counts['rules'] += \count($itemRules);
            $window = max(array_map(static fn (AlertRule $rule): int => $rule->evaluationWindowSeconds(), $itemRules));
            try {
                $series = $this->victoriaMetrics->rangeQuery(
                    $this->queryFactory->buildQuery($item, $node, new MetricLabelFilters()),
                    $evaluatedAt->modify(\sprintf('-%d seconds', $window)),
                    $evaluatedAt,
                    $item->intervalSeconds(),
                );
            } catch (\Throwable $exception) {
                $counts['errors'] += \count($itemRules);
                $this->logger->error('Alert evaluation metrics read failed.', [
                    'node' => (string) $node->id(),
                    'item' => $item->key(),
                    'exception' => $exception,
                ]);
                continue;
            }

            foreach ($itemRules as $rule) {
                $activeByIdentity = [];
                foreach ($this->incidents->findActiveForRuleAndNode((string) $rule->id(), (string) $node->id()) as $incident) {
                    $activeByIdentity[self::labelsKey($incident->labels())] = $incident;
                }
                if ([] === $series) {
                    ++$counts['noData'];
                    continue;
                }
                foreach ($series as $row) {
                    ++$counts['series'];
                    try {
                        $dimensionSeries = new VictoriaMetricsRangeSeries(
                            $this->queryFactory->dimensionLabels($item, $row->labels),
                            $row->points,
                        );
                        $result = $this->evaluator->evaluate($rule, $dimensionSeries, $evaluatedAt, isset($activeByIdentity[self::labelsKey($dimensionSeries->labels)]));
                        match ($result->status) {
                            AlertEvaluationStatus::FIRING => ++$counts['firing'],
                            AlertEvaluationStatus::OK => ++$counts['ok'],
                            AlertEvaluationStatus::NO_DATA => ++$counts['noData'],
                            AlertEvaluationStatus::ERROR => ++$counts['errors'],
                        };
                        $this->incidentManager->apply($node, $rule, $result);
                    } catch (\Throwable $exception) {
                        ++$counts['errors'];
                        $this->logger->error('Alert rule comparison failed.', [
                            'node' => (string) $node->id(),
                            'rule' => (string) $rule->id(),
                            'exception' => $exception,
                        ]);
                    }
                }
            }
        }

        return new AlertEvaluationReport(...$counts);
    }

    /** @param array<string, string> $labels */
    private static function labelsKey(array $labels): string
    {
        ksort($labels);

        return json_encode($labels, \JSON_THROW_ON_ERROR);
    }
}
