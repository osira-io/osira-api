<?php

declare(strict_types=1);

namespace App\Repository\Monitoring;

use App\Entity\Monitoring\ItemDefinition;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ItemDefinition> */
final class ItemDefinitionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ItemDefinition::class);
    }

    public function createOrderedQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('itemDefinition')
            ->orderBy('itemDefinition.key', 'ASC');
    }
}
