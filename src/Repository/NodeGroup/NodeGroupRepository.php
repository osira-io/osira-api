<?php

declare(strict_types=1);

namespace App\Repository\NodeGroup;

use App\Entity\NodeGroup\NodeGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<NodeGroup> */
final class NodeGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NodeGroup::class);
    }

    public function createOrderedQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('nodeGroup')
            ->orderBy('nodeGroup.name', 'ASC');
    }
}
