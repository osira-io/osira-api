<?php

declare(strict_types=1);

namespace App\Service\Alert;

use App\Entity\Alert\AlertOperator;
use App\Entity\Alert\AlertRule;
use App\Entity\Monitoring\ItemValueType;
use App\Service\Metrics\VictoriaMetricsRangeSeries;

final class AlertRuleEvaluator
{
    public function evaluate(AlertRule $rule, VictoriaMetricsRangeSeries $series, \DateTimeImmutable $evaluatedAt, bool $incidentIsFiring): AlertEvaluationResult
    {
        $points = array_values(array_filter(
            $series->points,
            static fn ($point): bool => $point->timestamp >= $evaluatedAt->modify(\sprintf('-%d seconds', $rule->evaluationWindowSeconds())) && $point->timestamp <= $evaluatedAt,
        ));
        usort($points, static fn ($left, $right): int => $left->timestamp <=> $right->timestamp);
        // VictoriaMetrics can return more than one raw point for the same instant (agent
        // retries, batch retransmission, or no dedup interval configured). requiredOccurrences
        // counts distinct collection instants, so collapse same-timestamp points, keeping the
        // last value observed for that instant.
        $points = self::deduplicateByTimestamp($points);
        $labels = self::publicLabels($series->labels);
        if ([] === $points) {
            return new AlertEvaluationResult(AlertEvaluationStatus::NO_DATA, null, $labels, $evaluatedAt);
        }

        $type = $rule->itemDefinition()->valueType();
        self::validateOperator($type, $rule->operator());
        $matching = 0;
        foreach ($points as $point) {
            if ($this->compare($point->value, $rule->expectedValue(), $type, $rule->operator())) {
                ++$matching;
            }
        }

        $last = $points[array_key_last($points)]->value;
        if ($matching >= $rule->requiredOccurrences()) {
            return new AlertEvaluationResult(AlertEvaluationStatus::FIRING, $last, $labels, $evaluatedAt, $matching);
        }

        if ($incidentIsFiring) {
            $recoveryThreshold = $rule->recoveryThreshold() ?? $rule->expectedValue();
            $recoveryOperator = null === $rule->recoveryThreshold()
                ? $rule->operator()->inverse()
                : match ($rule->operator()) {
                    AlertOperator::GT, AlertOperator::GTE => AlertOperator::LT,
                    AlertOperator::LT, AlertOperator::LTE => AlertOperator::GT,
                    AlertOperator::EQ => AlertOperator::NEQ,
                    AlertOperator::NEQ => AlertOperator::EQ,
                };
            $recovered = $this->compare($last, $recoveryThreshold, $type, $recoveryOperator);
            if (!$recovered) {
                return new AlertEvaluationResult(AlertEvaluationStatus::FIRING, $last, $labels, $evaluatedAt, $matching);
            }
        }

        return new AlertEvaluationResult(AlertEvaluationStatus::OK, $last, $labels, $evaluatedAt, $matching);
    }

    /** @param list<\App\Service\Metrics\VictoriaMetricsRangeSeriesPoint> $points
     * @return list<\App\Service\Metrics\VictoriaMetricsRangeSeriesPoint>
     */
    private static function deduplicateByTimestamp(array $points): array
    {
        $byTimestamp = [];
        foreach ($points as $point) {
            $byTimestamp[$point->timestamp->format('Y-m-d\TH:i:s.u')] = $point;
        }

        return array_values($byTimestamp);
    }

    private function compare(string $observed, string $threshold, ItemValueType $type, AlertOperator $operator): bool
    {
        if (\in_array($type, [ItemValueType::STRING, ItemValueType::BOOLEAN], true)) {
            if (ItemValueType::BOOLEAN === $type) {
                $observed = self::booleanValue($observed);
                $threshold = self::booleanValue($threshold);
            }

            return AlertOperator::EQ === $operator ? $observed === $threshold : $observed !== $threshold;
        }

        if (!is_numeric($observed) || !is_numeric($threshold)) {
            throw new InvalidAlertComparison(\sprintf('Values "%s" and "%s" are not valid %s values.', $observed, $threshold, $type->value));
        }
        if (ItemValueType::INTEGER === $type && (1 !== preg_match('/^-?\d+$/D', $observed) || 1 !== preg_match('/^-?\d+$/D', $threshold))) {
            throw new InvalidAlertComparison('Integer alert comparisons require integer values.');
        }

        $comparison = (float) $observed <=> (float) $threshold;

        return match ($operator) {
            AlertOperator::GT => $comparison > 0,
            AlertOperator::GTE => $comparison >= 0,
            AlertOperator::LT => $comparison < 0,
            AlertOperator::LTE => $comparison <= 0,
            AlertOperator::EQ => 0 === $comparison,
            AlertOperator::NEQ => 0 !== $comparison,
        };
    }

    /** The single source of truth for which AlertOperator values are valid for a given ItemValueType, reused by AlertRuleManager to validate a rule's operator at create/update time. */
    public static function validateOperator(ItemValueType $type, AlertOperator $operator): void
    {
        if (\in_array($type, [ItemValueType::STRING, ItemValueType::BOOLEAN], true) && !\in_array($operator, [AlertOperator::EQ, AlertOperator::NEQ], true)) {
            throw new InvalidAlertComparison(\sprintf('Operator "%s" is invalid for %s items.', $operator->value, $type->value));
        }
    }

    /** Validates that a raw string value is well-formed for the given ItemValueType, without comparing it to anything. Reused by AlertRuleManager to reject malformed expectedValue/recoveryThreshold at create/update time using the exact same rules `compare()` enforces at evaluation time. */
    public static function assertValidValueFormat(ItemValueType $type, string $value): void
    {
        if (\in_array($type, [ItemValueType::STRING, ItemValueType::BOOLEAN], true)) {
            if (ItemValueType::BOOLEAN === $type) {
                self::booleanValue($value);
            }

            return;
        }

        if (!is_numeric($value)) {
            throw new InvalidAlertComparison(\sprintf('Value "%s" is not a valid %s value.', $value, $type->value));
        }
        if (ItemValueType::INTEGER === $type && 1 !== preg_match('/^-?\d+$/D', $value)) {
            throw new InvalidAlertComparison('Integer alert comparisons require integer values.');
        }
    }

    private static function booleanValue(string $value): string
    {
        return match (strtolower($value)) {
            '1', 'true' => 'true',
            '0', 'false' => 'false',
            default => throw new InvalidAlertComparison(\sprintf('Value "%s" is not a valid boolean.', $value)),
        };
    }

    /** @param array<string, string> $labels
     * @return array<string, string>
     */
    private static function publicLabels(array $labels): array
    {
        unset($labels['__name__'], $labels['node_id']);
        ksort($labels);

        return $labels;
    }
}
