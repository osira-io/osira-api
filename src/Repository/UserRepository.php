<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/** @extends ServiceEntityRepository<User> */
final class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findOneByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => User::normalizeEmail($email)]);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new \InvalidArgumentException('Unsupported user type.');
        }

        $user->setPasswordHash($newHashedPassword, new \DateTimeImmutable());
        $this->getEntityManager()->flush();
    }

    public function createOrderedQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('user')
            ->leftJoin('user.businessRoles', 'role')
            ->addSelect('role')
            ->orderBy('user.email', 'ASC');
    }

    /** @return array{isSuperAdmin: bool, permissions: list<string>} */
    public function authorizationData(User $user): array
    {
        /** @var list<array{slug: string, code: string|null}> $rows */
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('role.slug AS slug', 'permission.code AS code')
            ->from(User::class, 'user')
            ->join('user.businessRoles', 'role')
            ->leftJoin('role.permissions', 'permission')
            ->where('user.id = :id')
            ->setParameter('id', $user->id(), UlidType::NAME)
            ->getQuery()
            ->getArrayResult();

        $permissions = [];
        $isSuperAdmin = false;
        foreach ($rows as $row) {
            $isSuperAdmin = $isSuperAdmin || 'super-admin' === $row['slug'];
            if (null !== $row['code']) {
                $permissions[$row['code']] = true;
            }
        }

        return ['isSuperAdmin' => $isSuperAdmin, 'permissions' => array_keys($permissions)];
    }
}
