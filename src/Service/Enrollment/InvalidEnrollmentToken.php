<?php

declare(strict_types=1);

namespace App\Service\Enrollment;

final class InvalidEnrollmentToken extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('The enrollment token is invalid.');
    }
}
