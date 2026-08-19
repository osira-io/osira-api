<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Dto\Rbac\RoleSummary;
use App\Dto\User\UserOutput;
use App\Entity\User\User;

final readonly class UserOutputFactory
{
    public function create(User $user): UserOutput
    {
        $roles = [];
        foreach ($user->businessRoles() as $role) {
            $roles[] = new RoleSummary((string) $role->id(), $role->name(), $role->slug(), $role->isSystem());
        }
        usort($roles, static fn (RoleSummary $left, RoleSummary $right): int => $left->name <=> $right->name);

        return new UserOutput(
            (string) $user->id(),
            $user->getUserIdentifier(),
            $roles,
            $user->createdAt(),
            $user->updatedAt(),
        );
    }
}
