<?php

declare(strict_types=1);

namespace App\Repository\Notification;

use App\Entity\Notification\NotificationRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<NotificationRule> */
final class NotificationRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationRule::class);
    }

    public function createOrderedQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('rule')->orderBy('rule.name', 'ASC')->addOrderBy('rule.id', 'ASC');
    }

    /** @return list<NotificationRule> */
    public function findEnabled(): array
    {
        return $this->findBy(['isEnabled' => true], ['name' => 'ASC']);
    }
}
