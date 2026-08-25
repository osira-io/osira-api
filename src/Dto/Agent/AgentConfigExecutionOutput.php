<?php

declare(strict_types=1);

namespace App\Dto\Agent;

final readonly class AgentConfigExecutionOutput
{
    public function __construct(
        public string $shell,
        public string $command,
    ) {
    }
}
