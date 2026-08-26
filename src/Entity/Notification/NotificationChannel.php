<?php

declare(strict_types=1);

namespace App\Entity\Notification;

use App\Repository\Notification\NotificationChannelRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: NotificationChannelRepository::class)]
#[ORM\Table(name: 'notification_channels')]
#[ORM\UniqueConstraint(name: 'uniq_notification_channels_name', columns: ['name'])]
final class NotificationChannel
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private readonly Ulid $id;

    /** @param list<string> $emailRecipients */
    public function __construct(
        #[ORM\Column(length: 128)] private string $name,
        #[ORM\Column(enumType: NotificationChannelType::class, length: 16)] private NotificationChannelType $type,
        #[ORM\Column(options: ['default' => true])] private bool $isEnabled,
        #[ORM\Column(type: Types::JSON)] private array $emailRecipients,
        #[ORM\Column(length: 2048, nullable: true)] private ?string $webhookUrl,
        #[ORM\Column(type: Types::TEXT, nullable: true)] private ?string $webhookSecretCiphertext,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)] private readonly \DateTimeImmutable $createdAt,
    ) {
        $this->id = new Ulid();
        $this->updatedAt = $createdAt;
    }

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function id(): Ulid
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function type(): NotificationChannelType
    {
        return $this->type;
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    /** @return list<string> */
    public function emailRecipients(): array
    {
        return $this->emailRecipients;
    }

    public function webhookUrl(): ?string
    {
        return $this->webhookUrl;
    }

    public function webhookSecretCiphertext(): ?string
    {
        return $this->webhookSecretCiphertext;
    }

    public function hasWebhookSecret(): bool
    {
        return null !== $this->webhookSecretCiphertext;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @param list<string> $emailRecipients */
    public function update(string $name, NotificationChannelType $type, bool $isEnabled, array $emailRecipients, ?string $webhookUrl, ?string $webhookSecretCiphertext, \DateTimeImmutable $now): void
    {
        $this->name = $name;
        $this->type = $type;
        $this->isEnabled = $isEnabled;
        $this->emailRecipients = $emailRecipients;
        $this->webhookUrl = $webhookUrl;
        $this->webhookSecretCiphertext = $webhookSecretCiphertext;
        $this->updatedAt = $now;
    }
}
