<?php

declare(strict_types=1);

namespace App\Repository\Sla;

use App\Entity\Sla\Sla;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Sla> */
final class SlaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Sla::class);
    }

    public function createOrderedQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('sla')->orderBy('sla.name', 'ASC')->addOrderBy('sla.id', 'ASC');
    }
}
