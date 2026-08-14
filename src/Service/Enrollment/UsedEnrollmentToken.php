<?php

declare(strict_types=1);

namespace App\Service\Enrollment;

final class UsedEnrollmentToken extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('The enrollment token has already been used.');
    }
}
