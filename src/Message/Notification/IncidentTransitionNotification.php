<?php

declare(strict_types=1);

namespace App\Message\Notification;

final readonly class IncidentTransitionNotification
{
    public function __construct(
        public string $incidentId,
        public string $event,
        public string $observedValue,
        public string $occurredAt,
    ) {
    }
}
