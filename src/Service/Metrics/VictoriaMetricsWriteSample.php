<?php

declare(strict_types=1);

namespace App\Service\Metrics;

final readonly class VictoriaMetricsWriteSample
{
    public function __construct(
        public string $nodeId,
        public string $itemKey,
        public string $value,
        public \DateTimeImmutable $collectedAt,
    ) {
    }
}
