<?php

declare(strict_types=1);

namespace App\Repository\Maintenance;

use App\Entity\Maintenance\MaintenanceWindow;
use App\Entity\Node\Node;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UlidType;

/** @extends ServiceEntityRepository<MaintenanceWindow> */
final class MaintenanceWindowRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MaintenanceWindow::class);
    }

    public function createOrderedQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('maintenanceWindow')
            ->orderBy('maintenanceWindow.startsAt', 'DESC')
            ->addOrderBy('maintenanceWindow.name', 'ASC')
            ->addOrderBy('maintenanceWindow.id', 'ASC');
    }

    /** @return list<MaintenanceWindow> */
    public function findActiveForNode(Node $node, \DateTimeImmutable $now): array
    {
        $targetExpressions = ['node.id = :nodeId'];
        foreach ($node->groups()->toArray() as $index => $group) {
            $targetExpressions[] = 'nodeGroup.id = :groupId'.$index;
        }

        $qb = $this->createQueryBuilder('maintenanceWindow')
            ->distinct()
            ->leftJoin('maintenanceWindow.nodes', 'node')
            ->leftJoin('maintenanceWindow.nodeGroups', 'nodeGroup')
            ->andWhere('maintenanceWindow.isEnabled = true')
            ->andWhere('maintenanceWindow.startsAt <= :now')
            ->andWhere('maintenanceWindow.endsAt > :now')
            ->andWhere(implode(' OR ', $targetExpressions))
            ->setParameter('now', $now->setTimezone(new \DateTimeZone('UTC')), Types::DATETIME_IMMUTABLE)
            ->setParameter('nodeId', $node->id(), UlidType::NAME)
            ->orderBy('maintenanceWindow.name', 'ASC')
            ->addOrderBy('maintenanceWindow.startsAt', 'ASC')
            ->addOrderBy('maintenanceWindow.id', 'ASC');
        foreach ($node->groups()->toArray() as $index => $group) {
            $qb->setParameter('groupId'.$index, $group->id(), UlidType::NAME);
        }

        $windows = [];
        foreach ($qb->getQuery()->toIterable() as $window) {
            if (!$window instanceof MaintenanceWindow) {
                throw new \LogicException('Unexpected maintenance window query result.');
            }
            $windows[] = $window;
        }

        return $windows;
    }
}
