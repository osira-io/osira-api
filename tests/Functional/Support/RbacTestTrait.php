<?php

declare(strict_types=1);

namespace App\Tests\Functional\Support;

use App\Entity\Rbac\Role;
use App\Entity\User\User;
use App\Repository\Rbac\RoleRepository;
use App\Service\Rbac\RbacCatalogSynchronizer;

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
