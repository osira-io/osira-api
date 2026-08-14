<?php

declare(strict_types=1);

namespace App\User\Presentation\Api\Factory;

use App\Rbac\Infrastructure\Security\PermissionChecker;
use App\User\Domain\Entity\User;
use App\User\Presentation\Api\Resource\CurrentUserOutput;
use App\User\Presentation\Api\Resource\CurrentUserRoleOutput;

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
