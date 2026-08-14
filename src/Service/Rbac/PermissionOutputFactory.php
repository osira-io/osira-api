<?php

declare(strict_types=1);

namespace App\Service\Rbac;

use App\Dto\Rbac\PermissionOutput;
use App\Entity\Rbac\Permission;

final readonly class PermissionOutputFactory
{
    public function create(Permission $permission): PermissionOutput
    {
        return new PermissionOutput(
            (string) $permission->id(),
            $permission->code(),
            $permission->name(),
            $permission->description(),
            $permission->category(),
            $permission->createdAt(),
            $permission->updatedAt(),
        );
    }
}
