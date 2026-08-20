<?php

declare(strict_types=1);

namespace App\Service\Agent;

use App\Entity\Agent\AgentCredential;
use App\Repository\Agent\AgentCredentialRepository;
use App\Security\Auth\TokenGenerator;
use App\Security\Auth\TokenHasher;

final readonly class AgentCredentialAuthenticator
{
    public function __construct(
        private AgentCredentialRepository $credentials,
        private TokenHasher $tokenHasher,
    ) {
    }

    public function authenticate(string $rawToken, \DateTimeImmutable $now): AgentCredential
    {
        if (!str_starts_with($rawToken, TokenGenerator::AGENT_PREFIX)) {
            throw new InvalidAgentCredential();
        }

        $credential = $this->credentials->findOneForAuthentication($this->tokenHasher->hash($rawToken));
        if (!$credential instanceof AgentCredential) {
            throw new InvalidAgentCredential();
        }

        if (!$credential->isActiveAt($now)) {
            throw new InvalidAgentCredential();
        }

        if (null !== $credential->agent()->revokedAt()) {
            throw new InvalidAgentCredential();
        }

        return $credential;
    }
}
