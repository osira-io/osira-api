<?php

declare(strict_types=1);

namespace App\Factory\Enrollment;

use App\Entity\Enrollment\EnrollmentToken;
use App\Service\Shared\Exception\ResourceValidationException;

final class EnrollmentTokenFactory
{
    public function create(string $tokenHash, \DateTimeImmutable $createdAt, \DateTimeImmutable $expiresAt): EnrollmentToken
    {
        $tokenHash = trim($tokenHash);
        if ('' === $tokenHash) {
            throw new ResourceValidationException('An enrollment token hash cannot be empty.');
        }

        if ($expiresAt <= $createdAt) {
            throw new ResourceValidationException('An enrollment token must expire after its creation time.');
        }

        return new EnrollmentToken($tokenHash, $createdAt, $expiresAt);
    }
}
