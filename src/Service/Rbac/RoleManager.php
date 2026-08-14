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
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Ulid;

final readonly class RoleManager
{
    public function __construct(private RoleRepository $roles, private PermissionRepository $permissions, private EntityManagerInterface $entityManager, private ClockInterface $clock)
    {
    }

    public function create(CreateRoleInput $input): Role
    {
        $name = self::normalizeName($input->name);
        $slug = self::normalizeSlug($input->slug ?? $name);
        $this->assertAvailable($name, $slug);
        $now = $this->clock->now();
        $role = new Role($name, $slug, self::normalizeDescription($input->description), false, $now);
        $role->replacePermissions($this->resolvePermissions($input->permissionCodes), $now);
        $this->entityManager->persist($role);
        $this->entityManager->flush();

        return $role;
    }

    public function update(string $id, UpdateRoleInput $input): Role
    {
        $role = $this->find($id);
        if (SystemRole::SUPER_ADMIN === $role->slug() && $input->arePermissionCodesProvided()) {
            throw new ConflictHttpException('The Super Admin role must retain every Osira permission.');
        }
        $name = $input->isNameProvided() ? self::normalizeName($input->getName() ?? '') : $role->name();
        $description = $input->isDescriptionProvided() ? self::normalizeDescription($input->getDescription()) : $role->description();
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
            throw new ConflictHttpException('System roles cannot be deleted.');
        }
        $this->entityManager->remove($role);
        $this->entityManager->flush();
    }

    private function find(string $id): Role
    {
        if (!Ulid::isValid($id)) {
            throw new NotFoundHttpException('Role not found.');
        }
        $role = $this->roles->find(new Ulid($id));
        if (!$role instanceof Role) {
            throw new NotFoundHttpException('Role not found.');
        }

        return $role;
    }

    private function assertAvailable(string $name, string $slug, ?Role $current = null): void
    {
        foreach ([['name' => $name], ['slug' => $slug]] as $criteria) {
            $existing = $this->roles->findOneBy($criteria);
            if ($existing instanceof Role && $existing !== $current) {
                throw new ConflictHttpException('A role with this name or slug already exists.');
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
                throw new UnprocessableEntityHttpException(\sprintf('Unknown permission "%s".', $code));
            }
            $permissions[] = $permission;
        }

        return $permissions;
    }

    private static function normalizeName(string $name): string
    {
        $name = trim($name);
        if ('' === $name) {
            throw new UnprocessableEntityHttpException('A role name cannot be empty.');
        }

        return $name;
    }

    private static function normalizeSlug(string $slug): string
    {
        $slug = mb_strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');
        if ('' === $slug) {
            throw new UnprocessableEntityHttpException('A role slug cannot be empty.');
        }

        return $slug;
    }

    private static function normalizeDescription(?string $description): ?string
    {
        if (null === $description) {
            return null;
        }
        $description = trim($description);

        return '' === $description ? null : $description;
    }
}
