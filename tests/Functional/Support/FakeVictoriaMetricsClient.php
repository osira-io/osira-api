<?php

declare(strict_types=1);

namespace App\Tests\Functional\Support;

use App\Service\Metrics\VictoriaMetricsClientInterface;
use App\Service\Metrics\VictoriaMetricsRangeSeries;
use App\Service\Metrics\VictoriaMetricsRangeSeriesPoint;
use App\Service\Metrics\VictoriaMetricsSample;

final class FakeVictoriaMetricsClient implements VictoriaMetricsClientInterface
{
    /** @var list<string> */
    public array $queries = [];

    /** @var list<VictoriaMetricsSample> */
    public array $instantResult = [];

    /** @var list<VictoriaMetricsRangeSeries> */
    public array $rangeResult = [];

    public ?\Throwable $instantException = null;
    public ?\Throwable $rangeException = null;

    /** @return list<VictoriaMetricsSample> */
    public function instantQuery(string $query): array
    {
        $this->queries[] = $query;
        if ($this->instantException instanceof \Throwable) {
            throw $this->instantException;
        }

        return $this->instantResult;
    }

    /** @return list<VictoriaMetricsRangeSeries> */
    public function rangeQuery(string $query, \DateTimeImmutable $from, \DateTimeImmutable $to, int $stepSeconds): array
    {
        $this->queries[] = \sprintf('%s|%s|%s|%d', $query, $from->format(\DATE_ATOM), $to->format(\DATE_ATOM), $stepSeconds);
        if ($this->rangeException instanceof \Throwable) {
            throw $this->rangeException;
        }

        return $this->rangeResult;
    }

    /** @param array<string, string> $labels */
    public static function sample(array $labels, string $value, string $timestamp = '2026-08-19T12:00:00+00:00'): VictoriaMetricsSample
    {
        return new VictoriaMetricsSample($labels, new \DateTimeImmutable($timestamp), $value);
    }

    /** @param array<string, string> $labels
     * @param list<array{0:string,1:string}> $points
     */
    public static function series(array $labels, array $points): VictoriaMetricsRangeSeries
    {
        return new VictoriaMetricsRangeSeries($labels, array_map(
            static fn (array $point): VictoriaMetricsRangeSeriesPoint => new VictoriaMetricsRangeSeriesPoint(new \DateTimeImmutable($point[0]), $point[1]),
            $points,
        ));
    }
}
