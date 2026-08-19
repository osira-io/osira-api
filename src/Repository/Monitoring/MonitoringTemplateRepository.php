<?php

declare(strict_types=1);

namespace App\Repository\Monitoring;

use App\Entity\Monitoring\MonitoringTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<MonitoringTemplate> */
final class MonitoringTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MonitoringTemplate::class);
    }

    public function createOrderedQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('monitoringTemplate')
            ->leftJoin('monitoringTemplate.itemDefinitions', 'itemDefinition')
            ->addSelect('itemDefinition')
            ->orderBy('monitoringTemplate.name', 'ASC');
    }
}
