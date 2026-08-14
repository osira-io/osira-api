<?php

declare(strict_types=1);

namespace App\Rbac\Presentation\Api\Factory;

use App\Rbac\Domain\Entity\Role;
use App\Rbac\Presentation\Api\Resource\RoleOutput;

final readonly class RoleOutputFactory
{
    public function __construct(private PermissionOutputFactory $permissionOutputFactory)
    {
    }

    public function create(Role $role): RoleOutput
    {
        $permissions = [];
        foreach ($role->permissions() as $permission) {
            $permissions[] = $this->permissionOutputFactory->create($permission);
        }
        usort($permissions, static fn ($left, $right): int => $left->code <=> $right->code);

        return new RoleOutput(
            (string) $role->id(),
            $role->name(),
            $role->slug(),
            $role->description(),
            $role->isSystem(),
            $permissions,
            $role->createdAt(),
            $role->updatedAt(),
        );
    }
}
