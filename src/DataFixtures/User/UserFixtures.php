<?php

declare(strict_types=1);

namespace App\DataFixtures\User;

use App\DataFixtures\Rbac\RbacFixtures;
use App\Entity\Rbac\Role;
use App\Entity\User\User;
use App\Factory\User\UserFactory;
use App\Repository\Rbac\RoleRepository;
use App\Security\Rbac\SystemRole;
use DH\Auditor\Auditor;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * FOR DEVELOPMENT ONLY. Seeds one user per system role plus one for the custom
 * "NOC Operator" role, all sharing the DEV-only password below. Never use these
 * accounts, or this password, outside a local/dev environment.
 */
final class UserFixtures extends Fixture implements DependentFixtureInterface
{
    /** FOR DEVELOPMENT ONLY — never use in production. */
    public const string DEV_PASSWORD = 'Osira123!';

    /** @var list<array{email: string, roleSlug: string}> */
    private const array USERS = [
        ['email' => 'admin@osira.local', 'roleSlug' => SystemRole::SUPER_ADMIN],
        ['email' => 'admin2@osira.local', 'roleSlug' => SystemRole::ADMIN],
        ['email' => 'operator@osira.local', 'roleSlug' => SystemRole::OPERATOR],
        ['email' => 'viewer@osira.local', 'roleSlug' => SystemRole::VIEWER],
        ['email' => 'noc@osira.local', 'roleSlug' => RbacFixtures::NOC_OPERATOR_ROLE_SLUG],
    ];

    public function __construct(
        private readonly RoleRepository $roles,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly Auditor $auditor,
        private readonly UserFactory $userFactory,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $this->auditor->getConfiguration()->disable();

        $now = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
        foreach (self::USERS as $definition) {
            $role = $this->roles->findOneBy(['slug' => $definition['roleSlug']]);
            \assert($role instanceof Role);

            $user = $this->userFactory->create($definition['email'], $now);
            $user->replaceBusinessRoles([$role], $now);
            $user->setPasswordHash($this->passwordHasher->hashPassword($user, self::DEV_PASSWORD), $now);
            $manager->persist($user);
        }
        $manager->flush();

        $this->auditor->getConfiguration()->enable();
    }

    /** @return array<class-string<\Doctrine\Common\DataFixtures\FixtureInterface>> */
    public function getDependencies(): array
    {
        return [RbacFixtures::class];
    }
}
