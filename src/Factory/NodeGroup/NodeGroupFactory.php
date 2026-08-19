<?php

declare(strict_types=1);

namespace App\Factory\NodeGroup;

use App\Entity\NodeGroup\NodeGroup;
use App\Service\Shared\Exception\ResourceValidationException;

final class NodeGroupFactory
{
    public function create(string $name, ?string $description, \DateTimeImmutable $createdAt): NodeGroup
    {
        return new NodeGroup(
            $this->normalizeName($name),
            $this->normalizeDescription($description),
            $createdAt,
        );
    }

    public function normalizeName(?string $name): string
    {
        $name = trim($name ?? '');
        if ('' === $name) {
            throw new ResourceValidationException('A node group name cannot be empty.');
        }

        return $name;
    }

    public function normalizeDescription(?string $description): ?string
    {
        if (null === $description) {
            return null;
        }

        $description = trim($description);

        return '' === $description ? null : $description;
    }
}
