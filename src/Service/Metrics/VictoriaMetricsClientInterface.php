<?php

declare(strict_types=1);

namespace App\Service\Metrics;

interface VictoriaMetricsClientInterface
{
    /** @param list<VictoriaMetricsWriteSample> $samples */
    public function importSamples(array $samples): void;

    /** @return list<VictoriaMetricsSample> */
    public function instantQuery(string $query): array;

    /** @return list<VictoriaMetricsRangeSeries> */
    public function rangeQuery(string $query, \DateTimeImmutable $from, \DateTimeImmutable $to, int $stepSeconds): array;
}
