<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Application\Rbac\RbacCatalogSynchronizer;
use App\Entity\Role;
use App\Entity\User;
use App\Repository\RoleRepository;

trait RbacTestTrait
{
    private function initializeRbac(): void
    {
        self::getContainer()->get(RbacCatalogSynchronizer::class)->synchronize();
    }

    private function assignSystemRole(User $user, string $slug): void
    {
        $role = self::getContainer()->get(RoleRepository::class)->findOneBy(['slug' => $slug]);
        self::assertInstanceOf(Role::class, $role);
        $user->replaceBusinessRoles([$role], new \DateTimeImmutable());
    }
}
