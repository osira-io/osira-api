<?php

declare(strict_types=1);

namespace App\Security;

final readonly class TokenHasher
{
    public function __construct(private string $pepper)
    {
        if ('' === $this->pepper) {
            throw new \InvalidArgumentException('APP_SECRET must be configured before tokens can be hashed.');
        }
    }

    public function hash(string $token): string
    {
        return hash_hmac('sha256', $token, $this->pepper);
    }

    public function verify(string $token, string $expectedHash): bool
    {
        return hash_equals($expectedHash, $this->hash($token));
    }
}
