<?php

declare(strict_types=1);

namespace App\Service\Enrollment;

use App\Entity\Enrollment\EnrollmentToken;

final readonly class IssuedEnrollmentToken
{
    public function __construct(
        public EnrollmentToken $enrollmentToken,
        public string $rawToken,
    ) {
    }
}
