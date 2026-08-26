<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Dto\Notification\CreateNotificationChannelInput;
use App\Dto\Notification\UpdateNotificationChannelInput;
use App\Entity\Notification\NotificationChannel;
use App\Entity\Notification\NotificationChannelType;
use App\Factory\Notification\NotificationChannelFactory;
use App\Repository\Notification\NotificationChannelRepository;
use App\Service\Shared\Exception\ResourceNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Ulid;

final readonly class NotificationChannelManager
{
    public function __construct(private NotificationChannelRepository $repository, private NotificationChannelFactory $factory, private EntityManagerInterface $entityManager, private ClockInterface $clock)
    {
    }

    public function create(CreateNotificationChannelInput $input): NotificationChannel
    {
        $channel = $this->factory->create($input->name, NotificationChannelType::from($input->type), $input->isEnabled, $input->emailRecipients, $input->webhookUrl, $input->webhookSecret, $this->clock->now());
        $this->entityManager->persist($channel);
        $this->entityManager->flush();

        return $channel;
    }

    public function update(string $id, UpdateNotificationChannelInput $input): NotificationChannel
    {
        $channel = $this->find($id);
        $type = null === $input->type ? $channel->type() : NotificationChannelType::from($input->type);
        $recipients = $input->emailRecipients ?? $channel->emailRecipients();
        $webhookUrl = $input->webhookUrl ?? $channel->webhookUrl();
        [$recipients, $webhookUrl] = $this->factory->validateConfiguration($type, $recipients, $webhookUrl);
        $secret = $channel->webhookSecretCiphertext();
        if ($input->clearWebhookSecret || NotificationChannelType::WEBHOOK !== $type) {
            $secret = null;
        } elseif (null !== $input->webhookSecret) {
            $secret = $this->factory->encryptSecret($input->webhookSecret);
        }
        $channel->update(null === $input->name ? $channel->name() : $this->factory->normalizeName($input->name), $type, $input->isEnabled ?? $channel->isEnabled(), $recipients, $webhookUrl, $secret, $this->clock->now());
        $this->entityManager->flush();

        return $channel;
    }

    public function delete(string $id): void
    {
        $this->entityManager->remove($this->find($id));
        $this->entityManager->flush();
    }

    public function find(string $id): NotificationChannel
    {
        $channel = Ulid::isValid($id) ? $this->repository->find(new Ulid($id)) : null;
        if (!$channel instanceof NotificationChannel) {
            throw new ResourceNotFoundException('Notification channel not found.');
        }

        return $channel;
    }
}
