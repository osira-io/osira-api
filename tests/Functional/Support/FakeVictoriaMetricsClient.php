<?php

declare(strict_types=1);

namespace App\Tests\Functional\Support;

use App\Service\Metrics\VictoriaMetricsClientInterface;
use App\Service\Metrics\VictoriaMetricsRangeSeries;
use App\Service\Metrics\VictoriaMetricsRangeSeriesPoint;
use App\Service\Metrics\VictoriaMetricsSample;

final class FakeVictoriaMetricsClient implements VictoriaMetricsClientInterface
{
    /** @var list<list<\App\Service\Metrics\VictoriaMetricsWriteSample>> */
    public array $importedBatches = [];

    /** @var list<string> */
    public array $queries = [];

    /** @var list<VictoriaMetricsSample> */
    public array $instantResult = [];

    /** @var list<VictoriaMetricsRangeSeries> */
    public array $rangeResult = [];

    public ?\Throwable $instantException = null;
    public ?\Throwable $rangeException = null;
    public ?\Throwable $importException = null;

    /**
     * When true, rangeQuery() is derived from importedBatches instead of the manually
     * set rangeResult. Every imported sample is kept as a raw point, including exact
     * duplicates (same node/item/timestamp) produced by retries — this deliberately
     * models a VictoriaMetrics deployment without a dedup interval configured, so
     * pipeline-level tests exercise the real risk instead of assuming it away.
     */
    public bool $deriveRangeFromImports = false;

    /** @param list<\App\Service\Metrics\VictoriaMetricsWriteSample> $samples */
    public function importSamples(array $samples): void
    {
        if ($this->importException instanceof \Throwable) {
            throw $this->importException;
        }
        $this->importedBatches[] = $samples;
    }

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
        if ($this->deriveRangeFromImports) {
            return $this->deriveSeriesFromImports($query, $from, $to);
        }

        return $this->rangeResult;
    }

    /** @return list<VictoriaMetricsRangeSeries> */
    private function deriveSeriesFromImports(string $query, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        if (1 !== preg_match('/node_id="([^"]*)"/', $query, $nodeMatch) || 1 !== preg_match('/item_key="([^"]*)"/', $query, $itemMatch)) {
            return [];
        }
        [, $nodeId] = $nodeMatch;
        [, $itemKey] = $itemMatch;

        $points = [];
        foreach ($this->importedBatches as $batch) {
            foreach ($batch as $sample) {
                if ($sample->nodeId === $nodeId && $sample->itemKey === $itemKey && $sample->collectedAt >= $from && $sample->collectedAt <= $to) {
                    $points[] = new VictoriaMetricsRangeSeriesPoint($sample->collectedAt, $sample->value);
                }
            }
        }
        if ([] === $points) {
            return [];
        }
        usort($points, static fn (VictoriaMetricsRangeSeriesPoint $left, VictoriaMetricsRangeSeriesPoint $right): int => $left->timestamp <=> $right->timestamp);

        return [new VictoriaMetricsRangeSeries(['node_id' => $nodeId, 'item_key' => $itemKey], $points)];
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
