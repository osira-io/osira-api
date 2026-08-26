<?php

declare(strict_types=1);

namespace App\Factory\Notification;

use App\Entity\Notification\NotificationChannel;
use App\Entity\Notification\NotificationChannelType;
use App\Service\Notification\WebhookSecretCipher;
use App\Service\Shared\Exception\ResourceValidationException;

final readonly class NotificationChannelFactory
{
    public function __construct(private WebhookSecretCipher $cipher)
    {
    }

    /** @param list<string> $emailRecipients */
    public function create(string $name, NotificationChannelType $type, bool $isEnabled, array $emailRecipients, ?string $webhookUrl, ?string $webhookSecret, \DateTimeImmutable $now): NotificationChannel
    {
        [$emailRecipients, $webhookUrl] = $this->validateConfiguration($type, $emailRecipients, $webhookUrl);

        return new NotificationChannel($this->normalizeName($name), $type, $isEnabled, $emailRecipients, $webhookUrl, null === $webhookSecret ? null : $this->cipher->encrypt($webhookSecret), $now);
    }

    public function normalizeName(string $name): string
    {
        $name = trim($name);
        if ('' === $name) {
            throw new ResourceValidationException('Notification channel name cannot be blank.');
        }

        return $name;
    }

    /** @param list<string> $emailRecipients
     * @return array{list<string>, ?string}
     */
    public function validateConfiguration(NotificationChannelType $type, array $emailRecipients, ?string $webhookUrl): array
    {
        $emailRecipients = array_values(array_unique(array_map(static fn (string $email): string => mb_strtolower(trim($email)), $emailRecipients)));
        if (NotificationChannelType::EMAIL === $type) {
            if ([] === $emailRecipients) {
                throw new ResourceValidationException('At least one email recipient is required.');
            }

            return [$emailRecipients, null];
        }
        $webhookUrl = null === $webhookUrl ? null : trim($webhookUrl);
        if (null === $webhookUrl || '' === $webhookUrl) {
            throw new ResourceValidationException('A webhook URL is required.');
        }

        return [[], $webhookUrl];
    }

    public function encryptSecret(?string $secret): ?string
    {
        return null === $secret ? null : $this->cipher->encrypt($secret);
    }
}
