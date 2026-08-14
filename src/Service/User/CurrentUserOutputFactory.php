<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Dto\User\CurrentUserOutput;
use App\Dto\User\CurrentUserRoleOutput;
use App\Entity\User\User;
use App\Service\Rbac\PermissionChecker;

final readonly class CurrentUserOutputFactory
{
    public function __construct(private PermissionChecker $permissionChecker)
    {
    }

    public function create(User $user): CurrentUserOutput
    {
        $roles = [];
        foreach ($user->businessRoles() as $role) {
            $roles[] = new CurrentUserRoleOutput((string) $role->id(), $role->name(), $role->slug());
        }
        usort($roles, static fn (CurrentUserRoleOutput $left, CurrentUserRoleOutput $right): int => $left->slug <=> $right->slug);

        return new CurrentUserOutput(
            (string) $user->id(),
            $user->getUserIdentifier(),
            $user->locale(),
            $roles,
            $this->permissionChecker->effectivePermissions($user),
        );
    }
}
