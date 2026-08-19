<?php

declare(strict_types=1);

namespace App\Service\Enrollment;

use App\Security\Auth\TokenGenerator;
use App\Security\Auth\TokenHasher;
use App\Factory\Enrollment\EnrollmentTokenFactory;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final readonly class EnrollmentTokenIssuer
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TokenGenerator $tokenGenerator,
        private TokenHasher $tokenHasher,
        private ClockInterface $clock,
        private int $ttlSeconds,
        private EnrollmentTokenFactory $enrollmentTokenFactory,
    ) {
    }

    public function issue(): IssuedEnrollmentToken
    {
        $rawToken = $this->tokenGenerator->generateEnrollmentToken();
        $now = $this->clock->now();
        $expiresAt = $now->add(new \DateInterval('PT'.$this->ttlSeconds.'S'));
        $enrollmentToken = $this->enrollmentTokenFactory->create($this->tokenHasher->hash($rawToken), $now, $expiresAt);

        $this->entityManager->persist($enrollmentToken);
        $this->entityManager->flush();

        return new IssuedEnrollmentToken($enrollmentToken, $rawToken);
    }
}
