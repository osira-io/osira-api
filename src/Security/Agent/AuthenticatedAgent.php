<?php

declare(strict_types=1);

namespace App\Security\Agent;

use App\Entity\Agent\Agent;
use App\Entity\Agent\AgentCredential;
use App\Entity\Node\Node;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class AuthenticatedAgent implements UserInterface
{
    public function __construct(private AgentCredential $credential)
    {
    }

    public function credential(): AgentCredential
    {
        return $this->credential;
    }

    public function agent(): Agent
    {
        return $this->credential->agent();
    }

    public function node(): Node
    {
        return $this->agent()->node();
    }

    public function getRoles(): array
    {
        return ['ROLE_AGENT'];
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->agent()->id();
    }
}
