<?php

declare(strict_types=1);

namespace App\Service\Agent;

final class InvalidAgentCredential extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('The agent credential is invalid.');
    }
}
