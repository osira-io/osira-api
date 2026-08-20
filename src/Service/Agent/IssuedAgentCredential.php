<?php

declare(strict_types=1);

namespace App\Service\Agent;

use App\Entity\Agent\AgentCredential;

final readonly class IssuedAgentCredential
{
    public function __construct(
        public AgentCredential $credential,
        public string $rawToken,
    ) {
    }
}
