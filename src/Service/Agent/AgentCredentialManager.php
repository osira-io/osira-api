<?php

declare(strict_types=1);

namespace App\Service\Agent;

use App\Dto\Agent\AgentCredentialOutput;
use App\Entity\Agent\AgentCredential;
use App\Factory\Enrollment\AgentCredentialFactory;
use App\Repository\Agent\AgentCredentialRepository;
use App\Security\Agent\AuthenticatedAgent;
use App\Security\Auth\TokenGenerator;
use App\Security\Auth\TokenHasher;
use App\Service\Shared\Exception\ResourceNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final readonly class AgentCredentialManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AgentCredentialRepository $credentials,
        private AgentCredentialFactory $agentCredentialFactory,
        private TokenGenerator $tokenGenerator,
        private TokenHasher $tokenHasher,
        private ClockInterface $clock,
    ) {
    }

    public function rotate(AuthenticatedAgent $authenticatedAgent): IssuedAgentCredential
    {
        return $this->entityManager->wrapInTransaction(function (EntityManagerInterface $entityManager) use ($authenticatedAgent): IssuedAgentCredential {
            $now = $this->clock->now();
            $currentCredential = $this->credentials->find($authenticatedAgent->credential()->id());
            if (!$currentCredential instanceof AgentCredential || !$currentCredential->isActiveAt($now) || null !== $currentCredential->agent()->revokedAt()) {
                throw new InvalidAgentCredential();
            }

            $currentCredential->revoke($now);
            $rawToken = $this->tokenGenerator->generateAgentToken();
            $newCredential = $this->agentCredentialFactory->create(
                $currentCredential->agent(),
                $this->tokenHasher->hash($rawToken),
                $now,
                null,
            );

            $entityManager->persist($newCredential);

            return new IssuedAgentCredential($newCredential, $rawToken);
        });
    }

    public function revoke(string $id): AgentCredentialOutput
    {
        $credential = $this->credentials->find($id);
        if (!$credential instanceof AgentCredential) {
            throw new ResourceNotFoundException('Agent credential not found.');
        }

        $credential->revoke($this->clock->now());
        $this->entityManager->flush();

        return $this->toOutput($credential);
    }

    public function toOutput(AgentCredential $credential): AgentCredentialOutput
    {
        return new AgentCredentialOutput(
            (string) $credential->id(),
            (string) $credential->agent()->id(),
            $credential->createdAt(),
            $credential->revokedAt(),
            $credential->expiresAt(),
        );
    }
}
