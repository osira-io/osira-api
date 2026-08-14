<?php

declare(strict_types=1);

namespace App\State;

use App\ApiResource\PermissionOutput;
use App\Entity\Permission;

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
