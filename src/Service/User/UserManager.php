<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Dto\User\CreateUserInput;
use App\Dto\User\UpdateUserInput;
use App\Entity\Rbac\Role;
use App\Entity\User\User;
use App\Repository\Rbac\RoleRepository;
use App\Repository\User\UserRepository;
use App\Security\Rbac\SystemRole;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Ulid;

final readonly class UserManager
{
    public function __construct(
        private UserRepository $users,
        private RoleRepository $roles,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private ClockInterface $clock,
    ) {
    }

    public function create(CreateUserInput $input): User
    {
        $email = User::normalizeEmail($input->email);
        $this->assertEmailAvailable($email);
        $now = $this->clock->now();
        $user = new User($email, ['ROLE_USER'], $now);
        $user->replaceBusinessRoles($this->resolveRoles($input->roleIds), $now);
        $user->setPasswordHash($this->passwordHasher->hashPassword($user, $input->password), $now);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    public function update(string $id, UpdateUserInput $input): User
    {
        $user = $this->find($id);
        $now = $this->clock->now();
        if ($input->isEmailProvided()) {
            $email = User::normalizeEmail($input->getEmail() ?? '');
            $this->assertEmailAvailable($email, $user);
            $user->updateEmail($email, $now);
        }
        if ($input->isPasswordProvided()) {
            $password = $input->getPassword();
            if (null === $password) {
                throw new UnprocessableEntityHttpException('The password cannot be null.');
            }
            $user->setPasswordHash($this->passwordHasher->hashPassword($user, $password), $now);
        }
        if ($input->areRoleIdsProvided()) {
            $roles = $this->resolveRoles($input->getRoleIds());
            $this->assertSuperAdminPreserved($user, $roles);
            $user->replaceBusinessRoles($roles, $now);
        }
        $this->entityManager->flush();

        return $user;
    }

    public function delete(string $id): void
    {
        $user = $this->find($id);
        if ($this->isSuperAdmin($user) && 0 === $this->roles->countSuperAdmins($user->id())) {
            throw new ConflictHttpException('The last Super Admin cannot be deleted.');
        }
        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }

    private function find(string $id): User
    {
        if (!Ulid::isValid($id)) {
            throw new NotFoundHttpException('User not found.');
        }
        $user = $this->users->find(new Ulid($id));
        if (!$user instanceof User) {
            throw new NotFoundHttpException('User not found.');
        }

        return $user;
    }

    private function assertEmailAvailable(string $email, ?User $current = null): void
    {
        $existing = $this->users->findOneByEmail($email);
        if ($existing instanceof User && $existing !== $current) {
            throw new ConflictHttpException('A user with this email already exists.');
        }
    }

    /** @param list<string> $ids
     * @return list<Role>
     */
    private function resolveRoles(array $ids): array
    {
        $roles = [];
        foreach (array_values(array_unique($ids)) as $id) {
            if (!Ulid::isValid($id)) {
                throw new UnprocessableEntityHttpException('An assigned role is invalid.');
            }
            $role = $this->roles->find(new Ulid($id));
            if (!$role instanceof Role) {
                throw new UnprocessableEntityHttpException('An assigned role does not exist.');
            }
            $roles[] = $role;
        }

        return $roles;
    }

    /** @param list<Role> $newRoles */
    private function assertSuperAdminPreserved(User $user, array $newRoles): void
    {
        if (!$this->isSuperAdmin($user)) {
            return;
        }
        foreach ($newRoles as $role) {
            if (SystemRole::SUPER_ADMIN === $role->slug()) {
                return;
            }
        }
        if (0 === $this->roles->countSuperAdmins($user->id())) {
            throw new ConflictHttpException('The Super Admin role cannot be removed from the last Super Admin.');
        }
    }

    private function isSuperAdmin(User $user): bool
    {
        foreach ($user->businessRoles() as $role) {
            if (SystemRole::SUPER_ADMIN === $role->slug()) {
                return true;
            }
        }

        return false;
    }
}
