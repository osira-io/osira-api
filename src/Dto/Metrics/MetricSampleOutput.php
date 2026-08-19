<?php

declare(strict_types=1);

namespace App\Dto\Metrics;

final readonly class MetricSampleOutput
{
    /** @param array<string, string> $labels */
    public function __construct(
        public string $metricKey,
        public array $labels,
        public \DateTimeImmutable $timestamp,
        public string $value,
    ) {
    }
}
