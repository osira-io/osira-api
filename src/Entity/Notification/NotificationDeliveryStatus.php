<?php

declare(strict_types=1);

namespace App\Entity\Notification;

enum NotificationDeliveryStatus: string
{
    case PENDING = 'pending';
    case SENT = 'sent';
}
