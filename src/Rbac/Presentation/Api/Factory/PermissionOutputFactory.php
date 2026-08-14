<?php

declare(strict_types=1);

namespace App\Rbac\Presentation\Api\Factory;

use App\Rbac\Domain\Entity\Permission;
use App\Rbac\Presentation\Api\Resource\PermissionOutput;

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
