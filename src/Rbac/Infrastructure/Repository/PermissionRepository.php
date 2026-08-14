<?php

declare(strict_types=1);

namespace App\Rbac\Infrastructure\Repository;

use App\Rbac\Domain\Entity\Permission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Permission> */
final class PermissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Permission::class);
    }

    public function createOrderedQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('permission')
            ->orderBy('permission.category', 'ASC')
            ->addOrderBy('permission.code', 'ASC');
    }
}
