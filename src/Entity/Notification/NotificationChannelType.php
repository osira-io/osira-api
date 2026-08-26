<?php

declare(strict_types=1);

namespace App\Entity\Notification;

enum NotificationChannelType: string
{
    case EMAIL = 'email';
    case WEBHOOK = 'webhook';
}
