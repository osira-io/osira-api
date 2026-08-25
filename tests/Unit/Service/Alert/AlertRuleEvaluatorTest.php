<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Alert;

use App\Entity\Alert\AlertOperator;
use App\Entity\Alert\AlertRule;
use App\Entity\Alert\AlertSeverity;
use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\ItemValueType;
use App\Service\Alert\AlertEvaluationStatus;
use App\Service\Alert\AlertRuleEvaluator;
use App\Service\Alert\InvalidAlertComparison;
use App\Service\Metrics\VictoriaMetricsRangeSeries;
use App\Service\Metrics\VictoriaMetricsRangeSeriesPoint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AlertRuleEvaluatorTest extends TestCase
{
    #[DataProvider('operatorCases')]
    public function testSupportsEveryOperator(AlertOperator $operator, string $threshold, string $value, AlertEvaluationStatus $expected): void
    {
        $now = new \DateTimeImmutable('2026-08-25T12:00:00+00:00');
        $rule = $this->rule(ItemValueType::FLOAT, $operator, $threshold, 300, 1);

        $result = (new AlertRuleEvaluator())->evaluate($rule, $this->series(['device' => 'cpu0'], [$value], $now), $now, false);

        self::assertSame($expected, $result->status);
        self::assertSame(['device' => 'cpu0'], $result->labels);
        self::assertSame($value, $result->observedValue);
    }

    /** @return iterable<string, array{AlertOperator, string, string, AlertEvaluationStatus}> */
    public static function operatorCases(): iterable
    {
        yield 'gt' => [AlertOperator::GT, '90', '91', AlertEvaluationStatus::FIRING];
        yield 'gte' => [AlertOperator::GTE, '90', '90', AlertEvaluationStatus::FIRING];
        yield 'lt' => [AlertOperator::LT, '10', '9', AlertEvaluationStatus::FIRING];
        yield 'lte' => [AlertOperator::LTE, '10', '10', AlertEvaluationStatus::FIRING];
        yield 'eq' => [AlertOperator::EQ, '10', '10', AlertEvaluationStatus::FIRING];
        yield 'neq' => [AlertOperator::NEQ, '10', '11', AlertEvaluationStatus::FIRING];
        yield 'ok' => [AlertOperator::GT, '90', '89', AlertEvaluationStatus::OK];
    }

    public function testRequiresEnoughMatchingSamplesInWindow(): void
    {
        $now = new \DateTimeImmutable('2026-08-25T12:00:00+00:00');
        $rule = $this->rule(ItemValueType::FLOAT, AlertOperator::GT, '90', 300, 3);
        $series = $this->series([], ['91', '89', '92', '93'], $now);

        self::assertSame(AlertEvaluationStatus::FIRING, (new AlertRuleEvaluator())->evaluate($rule, $series, $now, false)->status);
        self::assertSame(AlertEvaluationStatus::OK, (new AlertRuleEvaluator())->evaluate($rule, $this->series([], ['91', '89', '92'], $now), $now, false)->status);
    }

    public function testRecoveryThresholdProvidesHysteresis(): void
    {
        $now = new \DateTimeImmutable('2026-08-25T12:00:00+00:00');
        $rule = $this->rule(ItemValueType::FLOAT, AlertOperator::GT, '90', 300, 1, '80');
        $evaluator = new AlertRuleEvaluator();

        self::assertSame(AlertEvaluationStatus::FIRING, $evaluator->evaluate($rule, $this->series([], ['85'], $now), $now, true)->status);
        self::assertSame(AlertEvaluationStatus::FIRING, $evaluator->evaluate($rule, $this->series([], ['80'], $now), $now, true)->status);
        self::assertSame(AlertEvaluationStatus::OK, $evaluator->evaluate($rule, $this->series([], ['79'], $now), $now, true)->status);
    }

    public function testNoSamplesIsNoData(): void
    {
        $result = (new AlertRuleEvaluator())->evaluate(
            $this->rule(ItemValueType::FLOAT, AlertOperator::GT, '90', 300, 1),
            new VictoriaMetricsRangeSeries([], []),
            new \DateTimeImmutable('2026-08-25T12:00:00+00:00'),
            true,
        );

        self::assertSame(AlertEvaluationStatus::NO_DATA, $result->status);
    }

    public function testRejectsOrderedComparisonForStringItem(): void
    {
        $this->expectException(InvalidAlertComparison::class);

        (new AlertRuleEvaluator())->evaluate(
            $this->rule(ItemValueType::STRING, AlertOperator::GT, 'running', 60, 1),
            $this->series([], ['stopped'], new \DateTimeImmutable()),
            new \DateTimeImmutable(),
            false,
        );
    }

    private function rule(ItemValueType $type, AlertOperator $operator, string $threshold, int $window, int $occurrences, ?string $recovery = null): AlertRule
    {
        $now = new \DateTimeImmutable('2026-08-25T12:00:00+00:00');
        $item = new ItemDefinition('custom.test.metric', 'Metric', null, null, $type, 60, 5, 'printf 1', null, true, $now);

        return new AlertRule('Rule', 'Alert title', 'Alert message', $item, $operator, $threshold, $recovery, $window, $occurrences, AlertSeverity::WARNING, true, $now);
    }

    /** @param array<string, string> $labels
     * @param list<string> $values
     */
    private function series(array $labels, array $values, \DateTimeImmutable $now): VictoriaMetricsRangeSeries
    {
        return new VictoriaMetricsRangeSeries($labels, array_map(
            static fn (string $value, int $index): VictoriaMetricsRangeSeriesPoint => new VictoriaMetricsRangeSeriesPoint($now->modify(\sprintf('-%d seconds', \count($values) - $index)), $value),
            $values,
            array_keys($values),
        ));
    }
}
