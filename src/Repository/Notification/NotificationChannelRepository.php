<?php

declare(strict_types=1);

namespace App\Repository\Notification;

use App\Entity\Notification\NotificationChannel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<NotificationChannel> */
final class NotificationChannelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationChannel::class);
    }

    public function createOrderedQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('channel')->orderBy('channel.name', 'ASC')->addOrderBy('channel.id', 'ASC');
    }
}
