<?php

declare(strict_types=1);

namespace App\Repository\Notification;

use App\Entity\Incident\Incident;
use App\Entity\Notification\NotificationChannel;
use App\Entity\Notification\NotificationDelivery;
use App\Entity\Notification\NotificationEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<NotificationDelivery> */
final class NotificationDeliveryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationDelivery::class);
    }

    public function findForEvent(Incident $incident, NotificationEvent $event, NotificationChannel $channel): ?NotificationDelivery
    {
        return $this->findOneBy(['incident' => $incident, 'event' => $event, 'channel' => $channel]);
    }
}
