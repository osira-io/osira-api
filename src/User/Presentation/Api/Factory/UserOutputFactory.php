<?php

declare(strict_types=1);

namespace App\User\Presentation\Api\Factory;

use App\Rbac\Presentation\Api\Resource\RoleSummary;
use App\User\Domain\Entity\User;
use App\User\Presentation\Api\Resource\UserOutput;

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
