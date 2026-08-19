<?php

declare(strict_types=1);

namespace App\Repository\Rbac;

use App\Entity\Rbac\Role;
use App\Security\Rbac\SystemRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

/** @extends ServiceEntityRepository<Role> */
final class RoleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Role::class);
    }

    public function createOrderedQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('role')
            ->leftJoin('role.permissions', 'permission')
            ->addSelect('permission')
            ->orderBy('role.isSystem', 'DESC')
            ->addOrderBy('role.name', 'ASC');
    }

    public function countSuperAdmins(?Ulid $excludingUserId = null): int
    {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(DISTINCT user.id)')
            ->from('App\Entity\User\User', 'user')
            ->join('user.businessRoles', 'role')
            ->where('role.slug = :slug')
            ->setParameter('slug', SystemRole::SUPER_ADMIN);

        if (null !== $excludingUserId) {
            $queryBuilder->andWhere('user.id != :userId')->setParameter('userId', $excludingUserId, UlidType::NAME);
        }

        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }
}
