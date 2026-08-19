<?php

declare(strict_types=1);

namespace App\Service\Enrollment;

use App\Entity\Agent\Agent;
use App\Entity\Agent\AgentCredential;
use App\Entity\Node\Node;
use App\Security\Auth\TokenGenerator;
use App\Security\Auth\TokenHasher;
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
            $node = new Node($hostname, null, $os, $architecture, $now, $now);
            $agent = new Agent($node, $agentVersion, $now, $now);
            $rawAgentToken = $this->tokenGenerator->generateAgentToken();
            $credential = new AgentCredential($agent, $this->tokenHasher->hash($rawAgentToken), $now, null);

            $token->markUsed($now);
            $entityManager->persist($node);
            $entityManager->persist($agent);
            $entityManager->persist($credential);

            return new EnrollmentResult($node, $agent, $rawAgentToken);
        });
    }
}
