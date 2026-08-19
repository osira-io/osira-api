<?php

declare(strict_types=1);

namespace App\Service\Metrics;

final readonly class VictoriaMetricsRangeSeries
{
    /** @param array<string, string> $labels
     * @param list<VictoriaMetricsRangeSeriesPoint> $points
     */
    public function __construct(
        public array $labels,
        public array $points,
    ) {
    }
}
