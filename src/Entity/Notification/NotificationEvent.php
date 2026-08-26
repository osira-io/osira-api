<?php

declare(strict_types=1);

namespace App\Entity\Notification;

enum NotificationEvent: string
{
    case INCIDENT_OPENED = 'incident.firing';
    case INCIDENT_RESOLVED = 'incident.resolved';
}
