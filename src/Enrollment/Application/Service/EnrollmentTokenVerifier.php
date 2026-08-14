<?php

declare(strict_types=1);

namespace App\Enrollment\Application\Service;

use App\Auth\Infrastructure\Security\TokenGenerator;
use App\Auth\Infrastructure\Security\TokenHasher;
use App\Enrollment\Domain\Entity\EnrollmentToken;
use App\Enrollment\Domain\Exception\ExpiredEnrollmentToken;
use App\Enrollment\Domain\Exception\InvalidEnrollmentToken;
use App\Enrollment\Domain\Exception\UsedEnrollmentToken;
use App\Enrollment\Infrastructure\Repository\EnrollmentTokenRepository;

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
