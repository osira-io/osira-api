<?php

declare(strict_types=1);

namespace App\Dto\Notification;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateNotificationChannelInput
{
    #[Assert\Length(min: 1, max: 128)] public ?string $name = null;
    #[Assert\Choice(choices: ['email', 'webhook'])] public ?string $type = null;
    public ?bool $isEnabled = null;
    /** @var list<string>|null */
    #[Assert\Count(max: 100)] #[Assert\All([new Assert\Email()])] public ?array $emailRecipients = null;
    #[Assert\Length(max: 2048)] #[Assert\Url(protocols: ['http', 'https'])] public ?string $webhookUrl = null;
    #[Assert\Length(min: 16, max: 512)] public ?string $webhookSecret = null;
    public bool $clearWebhookSecret = false;
}
