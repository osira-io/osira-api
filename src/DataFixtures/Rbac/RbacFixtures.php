<?php

declare(strict_types=1);

namespace App\DataFixtures\Rbac;

use App\Entity\Rbac\Permission;
use App\Entity\Rbac\Role;
use App\Repository\Rbac\PermissionRepository;
use App\Repository\Rbac\RoleRepository;
use App\Security\Rbac\PermissionCode;
use App\Service\Rbac\Factory\RoleFactory;
use App\Service\Rbac\RbacCatalogSynchronizer;
use DH\Auditor\Auditor;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Seeds the RBAC catalog for development: the four system roles via the same
 * {@see RbacCatalogSynchronizer} the app uses at runtime (`osira:rbac:sync`), plus one
 * custom role — "NOC Operator" — so the frontend can exercise dynamic, non-system roles.
 */
final class RbacFixtures extends Fixture
{
    public const string NOC_OPERATOR_ROLE_SLUG = 'noc-operator';
    public const string NOC_OPERATOR_REFERENCE = 'role-noc-operator';

    public function __construct(
        private readonly RbacCatalogSynchronizer $synchronizer,
        private readonly PermissionRepository $permissions,
        private readonly RoleRepository $roles,
        private readonly Auditor $auditor,
        private readonly RoleFactory $roleFactory,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $this->auditor->getConfiguration()->disable();

        $this->synchronizer->synchronize();

        $now = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $permissionCodes = [
            PermissionCode::NODES_READ,
            PermissionCode::NODES_UPDATE,
            PermissionCode::NODE_GROUPS_READ,
            PermissionCode::AUDIT_LOGS_READ,
            PermissionCode::METRICS_READ,
        ];
        $permissions = array_map(function (string $code): Permission {
            $permission = $this->permissions->findOneBy(['code' => $code]);
            \assert($permission instanceof Permission);

            return $permission;
        }, $permissionCodes);

        $role = $this->roles->findOneBy(['slug' => self::NOC_OPERATOR_ROLE_SLUG]);
        if (!$role instanceof Role) {
            $role = $this->roleFactory->create(
                'NOC Operator',
                self::NOC_OPERATOR_ROLE_SLUG,
                'Monitors node health and reviews related audit history across the fleet.',
                false,
                $now,
            );
            $manager->persist($role);
        }
        $role->replacePermissions($permissions, $now);
        $manager->flush();

        $this->addReference(self::NOC_OPERATOR_REFERENCE, $role);

        $this->auditor->getConfiguration()->enable();
    }
}
