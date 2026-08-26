<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\Incident\Incident;
use App\Entity\Notification\NotificationEvent;

final readonly class IncidentNotificationPayloadFactory
{
    /** @return array<string, mixed> */
    public function create(NotificationEvent $event, Incident $incident, string $observedValue, \DateTimeImmutable $occurredAt, string $deliveryId): array
    {
        $node = $incident->node();
        $alertRule = $incident->alertRule();

        return [
            'deliveryId' => $deliveryId,
            'event' => $event->value,
            'occurredAt' => $occurredAt->format(\DATE_ATOM),
            'incident' => [
                'id' => (string) $incident->id(),
                'status' => NotificationEvent::INCIDENT_OPENED === $event ? 'firing' : 'resolved',
                'title' => $incident->title(),
                'message' => $incident->message(),
                'severity' => $incident->severity()->value,
                'observedValue' => $observedValue,
                'labels' => $incident->labels(),
                'firstTriggeredAt' => $incident->firstTriggeredAt()->format(\DATE_ATOM),
                'resolvedAt' => $incident->resolvedAt()?->format(\DATE_ATOM),
                'node' => [
                    'id' => (string) $node->id(),
                    'hostname' => $node->hostname(),
                    'displayName' => $node->displayName(),
                ],
                'alertRule' => [
                    'id' => (string) $alertRule->id(),
                    'name' => $alertRule->name(),
                ],
            ],
        ];
    }
}
