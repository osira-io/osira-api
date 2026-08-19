<?php

declare(strict_types=1);

namespace App\Service\Enrollment;

use App\Security\Auth\TokenGenerator;
use App\Security\Auth\TokenHasher;
use App\Factory\Enrollment\AgentCredentialFactory;
use App\Factory\Enrollment\AgentFactory;
use App\Factory\Node\NodeFactory;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final readonly class AgentEnrollmentService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EnrollmentTokenVerifier $tokenVerifier,
        private TokenGenerator $tokenGenerator,
        private TokenHasher $tokenHasher,
        private ClockInterface $clock,
        private NodeFactory $nodeFactory,
        private AgentFactory $agentFactory,
        private AgentCredentialFactory $agentCredentialFactory,
    ) {
    }

    public function enroll(
        string $enrollmentToken,
        string $hostname,
        string $os,
        string $architecture,
        string $agentVersion,
    ): EnrollmentResult {
        return $this->entityManager->wrapInTransaction(function (EntityManagerInterface $entityManager) use (
            $enrollmentToken,
            $hostname,
            $os,
            $architecture,
            $agentVersion,
        ): EnrollmentResult {
            $now = $this->clock->now();
            $token = $this->tokenVerifier->verifyForUse($enrollmentToken, $now);
            $node = $this->nodeFactory->create($hostname, null, $os, $architecture, $now, $now);
            $agent = $this->agentFactory->create($node, $agentVersion, $now, $now);
            $rawAgentToken = $this->tokenGenerator->generateAgentToken();
            $credential = $this->agentCredentialFactory->create($agent, $this->tokenHasher->hash($rawAgentToken), $now, null);

            $token->markUsed($now);
            $entityManager->persist($node);
            $entityManager->persist($agent);
            $entityManager->persist($credential);

            return new EnrollmentResult($node, $agent, $rawAgentToken);
        });
    }
}
