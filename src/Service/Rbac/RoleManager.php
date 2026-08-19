<?php

declare(strict_types=1);

namespace App\Service\Rbac;

use App\Dto\Rbac\CreateRoleInput;
use App\Dto\Rbac\UpdateRoleInput;
use App\Entity\Rbac\Permission;
use App\Entity\Rbac\Role;
use App\Repository\Rbac\PermissionRepository;
use App\Repository\Rbac\RoleRepository;
use App\Security\Rbac\SystemRole;
use App\Factory\Rbac\RoleFactory;
use App\Service\Shared\Exception\ResourceConflictException;
use App\Service\Shared\Exception\ResourceNotFoundException;
use App\Service\Shared\Exception\ResourceValidationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Ulid;

final readonly class RoleManager
{
    public function __construct(
        private RoleRepository $roles,
        private PermissionRepository $permissions,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
        private RoleFactory $roleFactory,
    ) {
    }

    public function create(CreateRoleInput $input): Role
    {
        $name = $this->roleFactory->normalizeName($input->name);
        $slug = $this->roleFactory->normalizeSlug($input->slug ?? $name);
        $this->assertAvailable($name, $slug);
        $now = $this->clock->now();
        $role = $this->roleFactory->create($name, $slug, $input->description, false, $now);
        $role->replacePermissions($this->resolvePermissions($input->permissionCodes), $now);
        $this->entityManager->persist($role);
        $this->entityManager->flush();

        return $role;
    }

    public function update(string $id, UpdateRoleInput $input): Role
    {
        $role = $this->find($id);
        if (SystemRole::SUPER_ADMIN === $role->slug() && $input->arePermissionCodesProvided()) {
            throw new ResourceConflictException('The Super Admin role must retain every Osira permission.');
        }
        $name = $input->isNameProvided() ? $this->roleFactory->normalizeName($input->getName() ?? '') : $role->name();
        $description = $input->isDescriptionProvided() ? $this->roleFactory->normalizeDescription($input->getDescription()) : $role->description();
        $this->assertAvailable($name, $role->slug(), $role);
        $now = $this->clock->now();
        $role->update($name, $description, $now);
        if ($input->arePermissionCodesProvided()) {
            $role->replacePermissions($this->resolvePermissions($input->getPermissionCodes()), $now);
        }
        $this->entityManager->flush();

        return $role;
    }

    public function delete(string $id): void
    {
        $role = $this->find($id);
        if ($role->isSystem()) {
            throw new ResourceConflictException('System roles cannot be deleted.');
        }
        $this->entityManager->remove($role);
        $this->entityManager->flush();
    }

    private function find(string $id): Role
    {
        if (!Ulid::isValid($id)) {
            throw new ResourceNotFoundException('Role not found.');
        }
        $role = $this->roles->find(new Ulid($id));
        if (!$role instanceof Role) {
            throw new ResourceNotFoundException('Role not found.');
        }

        return $role;
    }

    private function assertAvailable(string $name, string $slug, ?Role $current = null): void
    {
        foreach ([['name' => $name], ['slug' => $slug]] as $criteria) {
            $existing = $this->roles->findOneBy($criteria);
            if ($existing instanceof Role && $existing !== $current) {
                throw new ResourceConflictException('A role with this name or slug already exists.');
            }
        }
    }

    /** @param list<string> $codes
     * @return list<Permission>
     */
    private function resolvePermissions(array $codes): array
    {
        $permissions = [];
        foreach (array_values(array_unique($codes)) as $code) {
            $permission = $this->permissions->findOneBy(['code' => $code]);
            if (!$permission instanceof Permission) {
                throw new ResourceValidationException(\sprintf('Unknown permission "%s".', $code));
            }
            $permissions[] = $permission;
        }

        return $permissions;
    }
}
