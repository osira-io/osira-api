<?php

declare(strict_types=1);

namespace App\Dto\Agent;

final readonly class AgentConfigNodeOutput
{
    public function __construct(
        public string $id,
        public string $hostname,
    ) {
    }
}
