<?php

declare(strict_types=1);

namespace App\Dto\Metrics;

final readonly class MetricPointOutput
{
    public function __construct(
        public \DateTimeImmutable $timestamp,
        public string $value,
    ) {
    }
}
