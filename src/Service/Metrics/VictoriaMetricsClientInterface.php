<?php

declare(strict_types=1);

namespace App\Service\Metrics;

interface VictoriaMetricsClientInterface
{
    /** @return list<VictoriaMetricsSample> */
    public function instantQuery(string $query): array;

    /** @return list<VictoriaMetricsRangeSeries> */
    public function rangeQuery(string $query, \DateTimeImmutable $from, \DateTimeImmutable $to, int $stepSeconds): array;
}
