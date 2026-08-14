<?php

declare(strict_types=1);

namespace App\Enrollment\Domain\Exception;

final class InvalidEnrollmentToken extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('The enrollment token is invalid.');
    }
}
