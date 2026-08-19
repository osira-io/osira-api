<?php

declare(strict_types=1);

namespace App\Service\Enrollment\Factory;

use App\Entity\Agent\Agent;
use App\Entity\Node\Node;
use App\Service\Shared\Exception\ResourceValidationException;

final class AgentFactory
{
    public function create(Node $node, string $version, \DateTimeImmutable $installedAt, ?\DateTimeImmutable $createdAt = null): Agent
    {
        $version = trim($version);
        if ('' === $version) {
            throw new ResourceValidationException('An agent version cannot be empty.');
        }

        return new Agent($node, $version, $installedAt, $createdAt ?? $installedAt);
    }
}
