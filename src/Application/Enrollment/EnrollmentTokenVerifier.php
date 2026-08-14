<?php

declare(strict_types=1);

namespace App\Application\Enrollment;

use App\Application\Exception\ExpiredEnrollmentToken;
use App\Application\Exception\InvalidEnrollmentToken;
use App\Application\Exception\UsedEnrollmentToken;
use App\Entity\EnrollmentToken;
use App\Repository\EnrollmentTokenRepository;
use App\Security\TokenGenerator;
use App\Security\TokenHasher;

final readonly class EnrollmentTokenVerifier
{
    public function __construct(
        private EnrollmentTokenRepository $repository,
        private TokenHasher $tokenHasher,
    ) {
    }

    public function verifyForUse(string $rawToken, \DateTimeImmutable $now): EnrollmentToken
    {
        if (!str_starts_with($rawToken, TokenGenerator::ENROLLMENT_PREFIX)) {
            throw new InvalidEnrollmentToken();
        }

        $token = $this->repository->findOneByHashForUpdate($this->tokenHasher->hash($rawToken));

        if (null === $token || !$this->tokenHasher->verify($rawToken, $token->tokenHash())) {
            throw new InvalidEnrollmentToken();
        }

        if (null !== $token->usedAt()) {
            throw new UsedEnrollmentToken();
        }

        if ($token->isExpiredAt($now)) {
            throw new ExpiredEnrollmentToken();
        }

        return $token;
    }
}
