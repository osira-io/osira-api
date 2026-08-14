<?php

declare(strict_types=1);

namespace App\Application\Enrollment;

use App\Entity\EnrollmentToken;

final readonly class IssuedEnrollmentToken
{
    public function __construct(
        public EnrollmentToken $enrollmentToken,
        public string $rawToken,
    ) {
    }
}
