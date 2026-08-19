<?php

declare(strict_types=1);

namespace App\Factory\Enrollment;

use App\Entity\Agent\Agent;
use App\Entity\Agent\AgentCredential;
use App\Service\Shared\Exception\ResourceValidationException;

final class AgentCredentialFactory
{
    public function create(
        Agent $agent,
        string $secretHash,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $expiresAt,
    ): AgentCredential {
        $secretHash = trim($secretHash);
        if ('' === $secretHash) {
            throw new ResourceValidationException('An agent credential hash cannot be empty.');
        }

        return new AgentCredential($agent, $secretHash, $createdAt, $expiresAt);
    }
}
