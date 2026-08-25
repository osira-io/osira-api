<?php

declare(strict_types=1);

namespace App\Repository\Alert;

use App\Entity\Alert\AlertRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AlertRule> */
final class AlertRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AlertRule::class);
    }
}
