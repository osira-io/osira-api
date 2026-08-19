<?php

declare(strict_types=1);

namespace App\Dto\Monitoring;

final readonly class ItemDefinitionSummary
{
    public function __construct(
        public string $id,
        public string $key,
        public string $name,
        public string $valueType,
        public bool $isEnabled,
    ) {
    }
}
