<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Node;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Node> */
final class NodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Node::class);
    }

    /** @return list<Node> */
    public function findAllOrdered(): array
    {
        /** @var list<Node> $nodes */
        $nodes = $this->createQueryBuilder('node')
            ->orderBy('node.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $nodes;
    }
}
