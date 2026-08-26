<?php

declare(strict_types=1);

namespace App\Repository\Alert;

use App\Entity\Alert\AlertRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

/** @extends ServiceEntityRepository<AlertRule> */
final class AlertRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AlertRule::class);
    }

    public function createOrderedQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('alertRule')
            ->leftJoin('alertRule.itemDefinition', 'itemDefinition')
            ->addSelect('itemDefinition')
            ->orderBy('alertRule.name', 'ASC')
            ->addOrderBy('alertRule.id', 'ASC');
    }

    /** Eagerly joins itemDefinition to avoid lazy-loading a ManyToOne proxy for a readonly-identifier entity. */
    public function findWithItemDefinition(Ulid $id): ?AlertRule
    {
        $alertRule = $this->createQueryBuilder('alertRule')
            ->addSelect('itemDefinition')
            ->join('alertRule.itemDefinition', 'itemDefinition')
            ->andWhere('alertRule.id = :id')
            ->setParameter('id', $id, UlidType::NAME)
            ->getQuery()->getOneOrNullResult();

        return $alertRule instanceof AlertRule ? $alertRule : null;
    }
}
