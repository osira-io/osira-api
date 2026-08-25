<?php

declare(strict_types=1);

namespace App\Dto\Agent;

final readonly class AgentConfigItemOutput
{
    public function __construct(
        public string $key,
        public string $valueType,
        public ?string $unit,
        public int $intervalSeconds,
        public ?int $timeoutSeconds,
        public AgentConfigExecutionOutput $execution,
    ) {
    }
}
