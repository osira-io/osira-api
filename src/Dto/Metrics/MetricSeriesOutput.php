<?php

declare(strict_types=1);

namespace App\Dto\Metrics;

final readonly class MetricSeriesOutput
{
    /** @param array<string, string> $labels
     * @param list<MetricPointOutput> $points
     */
    public function __construct(
        public string $metricKey,
        public array $labels,
        public array $points,
    ) {
    }
}
