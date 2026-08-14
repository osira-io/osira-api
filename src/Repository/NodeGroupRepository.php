<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\NodeGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<NodeGroup> */
final class NodeGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NodeGroup::class);
    }

    /** @return list<NodeGroup> */
    public function findAllOrdered(): array
    {
        /** @var list<NodeGroup> $groups */
        $groups = $this->createQueryBuilder('nodeGroup')
            ->orderBy('nodeGroup.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $groups;
    }
}
