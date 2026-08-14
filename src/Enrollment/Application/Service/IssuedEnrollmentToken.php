<?php

declare(strict_types=1);

namespace App\Enrollment\Application\Service;

use App\Enrollment\Domain\Entity\EnrollmentToken;

final readonly class IssuedEnrollmentToken
{
    public function __construct(
        public EnrollmentToken $enrollmentToken,
        public string $rawToken,
    ) {
    }
}
