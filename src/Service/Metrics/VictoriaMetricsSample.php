<?php

declare(strict_types=1);

namespace App\Service\Metrics;

final readonly class VictoriaMetricsSample
{
    /** @param array<string, string> $labels */
    public function __construct(
        public array $labels,
        public \DateTimeImmutable $timestamp,
        public string $value,
    ) {
    }
}
