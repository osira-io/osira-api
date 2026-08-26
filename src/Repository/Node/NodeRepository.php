<?php

declare(strict_types=1);

namespace App\Repository\Node;

use App\Entity\Node\Node;
use App\Entity\NodeGroup\NodeGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UlidType;

/** @extends ServiceEntityRepository<Node> */
final class NodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Node::class);
    }

    public function createOrderedQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('node')
            ->orderBy('node.createdAt', 'DESC')
            ->addOrderBy('node.id', 'ASC');
    }

    /**
     * @param list<NodeGroup> $groups
     *
     * @return list<Node>
     */
    public function findForGroups(array $groups): array
    {
        if ([] === $groups) {
            return [];
        }

        $queryBuilder = $this->createQueryBuilder('node')
            ->orderBy('node.hostname', 'ASC')
            ->addOrderBy('node.id', 'ASC');
        $groupExpressions = [];
        foreach ($groups as $index => $group) {
            $parameter = 'group'.$index;
            $groupExpressions[] = ':'.$parameter.' MEMBER OF node.groups';
            $queryBuilder->setParameter($parameter, $group->id(), UlidType::NAME);
        }
        $results = $queryBuilder->andWhere(implode(' OR ', $groupExpressions))->getQuery()->getResult();

        $nodes = [];
        foreach (\is_array($results) ? $results : [] as $result) {
            if ($result instanceof Node) {
                $nodes[] = $result;
            }
        }

        return $nodes;
    }
}
