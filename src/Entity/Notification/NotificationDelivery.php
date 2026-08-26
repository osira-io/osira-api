<?php

declare(strict_types=1);

namespace App\Entity\Notification;

use App\Entity\Incident\Incident;
use App\Repository\Notification\NotificationDeliveryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: NotificationDeliveryRepository::class)]
#[ORM\Table(name: 'notification_deliveries')]
#[ORM\UniqueConstraint(name: 'uniq_notification_delivery_event_channel', columns: ['incident_id', 'event', 'channel_id'])]
final class NotificationDelivery
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private readonly Ulid $id;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private readonly Incident $incident,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
        private readonly NotificationChannel $channel,
        #[ORM\Column(enumType: NotificationEvent::class, length: 32)] private readonly NotificationEvent $event,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)] private readonly \DateTimeImmutable $createdAt,
    ) {
        $this->id = new Ulid();
        $this->status = NotificationDeliveryStatus::PENDING;
        $this->sentAt = null;
    }

    #[ORM\Column(enumType: NotificationDeliveryStatus::class, length: 16)]
    private NotificationDeliveryStatus $status;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $sentAt;

    public function id(): Ulid
    {
        return $this->id;
    }

    public function incident(): Incident
    {
        return $this->incident;
    }

    public function channel(): NotificationChannel
    {
        return $this->channel;
    }

    public function event(): NotificationEvent
    {
        return $this->event;
    }

    public function status(): NotificationDeliveryStatus
    {
        return $this->status;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function sentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function markSent(\DateTimeImmutable $now): void
    {
        $this->status = NotificationDeliveryStatus::SENT;
        $this->sentAt = $now;
    }
}
