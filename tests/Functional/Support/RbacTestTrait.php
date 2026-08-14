<?php

declare(strict_types=1);

namespace App\Tests\Functional\Support;

use App\Rbac\Application\Service\RbacCatalogSynchronizer;
use App\Rbac\Domain\Entity\Role;
use App\Rbac\Infrastructure\Repository\RoleRepository;
use App\User\Domain\Entity\User;

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
