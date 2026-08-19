<?php

declare(strict_types=1);

namespace App\Service\Metrics;

final readonly class VictoriaMetricsRangeSeriesPoint
{
    public function __construct(
        public \DateTimeImmutable $timestamp,
        public string $value,
    ) {
    }
}
