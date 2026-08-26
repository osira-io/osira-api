<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Dto\Notification\NotificationChannelOutput;
use App\Entity\Notification\NotificationChannel;

final readonly class NotificationChannelOutputFactory
{
    public function create(NotificationChannel $channel): NotificationChannelOutput
    {
        return new NotificationChannelOutput((string) $channel->id(), $channel->name(), $channel->type()->value, $channel->isEnabled(), $channel->emailRecipients(), $channel->webhookUrl(), $channel->hasWebhookSecret(), $channel->createdAt(), $channel->updatedAt());
    }
}
