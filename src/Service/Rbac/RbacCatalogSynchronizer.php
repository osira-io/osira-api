<?php

declare(strict_types=1);

namespace App\Service\Rbac;

use App\Entity\Rbac\Permission;
use App\Entity\Rbac\Role;
use App\Repository\Rbac\PermissionRepository;
use App\Repository\Rbac\RoleRepository;
use App\Security\Rbac\PermissionCode;
use App\Security\Rbac\SystemRole;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final readonly class RbacCatalogSynchronizer
{
    public function __construct(
        private PermissionRepository $permissions,
        private RoleRepository $roles,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    public function synchronize(): void
    {
        $now = $this->clock->now();
        $permissionsByCode = [];
        foreach (PermissionCode::catalog() as $code => $definition) {
            $permission = $this->permissions->findOneBy(['code' => $code]);
            if (!$permission instanceof Permission) {
                $permission = new Permission($code, $definition['name'], $definition['description'], $definition['category'], $now);
                $this->entityManager->persist($permission);
            } else {
                $permission->synchronize($definition['name'], $definition['description'], $definition['category'], $now);
            }
            $permissionsByCode[$code] = $permission;
        }

        foreach (SystemRole::catalog() as $slug => $definition) {
            $role = $this->roles->findOneBy(['slug' => $slug]);
            if (!$role instanceof Role) {
                $role = new Role($definition['name'], $slug, $definition['description'], true, $now);
                $this->entityManager->persist($role);
            } else {
                $role->update($definition['name'], $definition['description'], $now);
            }
            $role->replacePermissions(array_map(
                static fn (string $code): Permission => $permissionsByCode[$code],
                $definition['permissions'],
            ), $now);
        }

        $this->entityManager->flush();
    }
}
