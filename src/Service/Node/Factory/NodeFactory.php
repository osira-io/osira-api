<?php

declare(strict_types=1);

namespace App\Service\Node\Factory;

use App\Entity\Node\Node;
use App\Service\Shared\Exception\ResourceValidationException;

final class NodeFactory
{
    public function create(
        string $hostname,
        ?string $displayName,
        string $os,
        string $architecture,
        \DateTimeImmutable $firstSeenAt,
        ?\DateTimeImmutable $createdAt = null,
    ): Node {
        return new Node(
            $this->normalizeRequiredValue($hostname, 'A node hostname cannot be empty.'),
            $this->normalizeNullableValue($displayName),
            $this->normalizeRequiredValue($os, 'A node operating system cannot be empty.'),
            $this->normalizeRequiredValue($architecture, 'A node architecture cannot be empty.'),
            $firstSeenAt,
            $createdAt ?? $firstSeenAt,
        );
    }

    private function normalizeRequiredValue(string $value, string $message): string
    {
        $value = trim($value);
        if ('' === $value) {
            throw new ResourceValidationException($message);
        }

        return $value;
    }

    private function normalizeNullableValue(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
