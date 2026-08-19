<?php

declare(strict_types=1);

namespace App\Dto\Monitoring;

final readonly class MonitoringTemplateSummary
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public bool $isEnabled,
    ) {
    }
}
