<?php

declare(strict_types=1);

namespace App\Factory\Rbac;

use App\Entity\Rbac\Role;
use App\Service\Shared\Exception\ResourceValidationException;

final class RoleFactory
{
    public function create(string $name, ?string $slug, ?string $description, bool $isSystem, \DateTimeImmutable $createdAt): Role
    {
        $normalizedName = $this->normalizeName($name);

        return new Role(
            $normalizedName,
            $this->normalizeSlug($slug ?? $normalizedName),
            $this->normalizeDescription($description),
            $isSystem,
            $createdAt,
        );
    }

    public function normalizeName(string $name): string
    {
        $name = trim($name);
        if ('' === $name) {
            throw new ResourceValidationException('A role name cannot be empty.');
        }

        return $name;
    }

    public function normalizeSlug(string $slug): string
    {
        $slug = mb_strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');
        if ('' === $slug) {
            throw new ResourceValidationException('A role slug cannot be empty.');
        }

        return $slug;
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
