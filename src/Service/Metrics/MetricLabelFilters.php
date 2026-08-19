<?php

declare(strict_types=1);

namespace App\Service\Metrics;

final readonly class MetricLabelFilters
{
    public function __construct(
        public ?string $device = null,
        public ?string $interface = null,
        public ?string $container = null,
    ) {
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return array_filter([
            'device' => $this->device,
            'interface' => $this->interface,
            'container' => $this->container,
        ], static fn (?string $value): bool => null !== $value);
    }
}
