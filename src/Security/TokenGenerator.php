<?php

declare(strict_types=1);

namespace App\Security;

use Random\Randomizer;

final readonly class TokenGenerator
{
    public const string AGENT_PREFIX = 'osi_agent_';
    public const string ENROLLMENT_PREFIX = 'osi_enroll_';

    public function generateEnrollmentToken(): string
    {
        return $this->generate(self::ENROLLMENT_PREFIX);
    }

    public function generateAgentToken(): string
    {
        return $this->generate(self::AGENT_PREFIX);
    }

    private function generate(string $prefix): string
    {
        $entropy = new Randomizer()->getBytes(32);
        $encoded = rtrim(strtr(base64_encode($entropy), '+/', '-_'), '=');

        return $prefix.$encoded;
    }
}
