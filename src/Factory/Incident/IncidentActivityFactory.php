<?php

declare(strict_types=1);

namespace App\Factory\Incident;

use App\Entity\Incident\Incident;
use App\Entity\Incident\IncidentActivity;
use App\Entity\Incident\IncidentActivityType;
use App\Entity\User\User;

final class IncidentActivityFactory
{
    public function acknowledged(Incident $incident, User $actor, ?string $message, \DateTimeImmutable $now): IncidentActivity
    {
        return new IncidentActivity($incident, $actor, (string) $actor->id(), $actor->getUserIdentifier(), IncidentActivityType::ACKNOWLEDGED, $this->normalizeOptionalMessage($message), (string) $incident->id(), $now);
    }

    public function comment(Incident $incident, User $actor, string $message, \DateTimeImmutable $now): IncidentActivity
    {
        return new IncidentActivity($incident, $actor, (string) $actor->id(), $actor->getUserIdentifier(), IncidentActivityType::COMMENT, trim($message), null, $now);
    }

    private function normalizeOptionalMessage(?string $message): ?string
    {
        if (null === $message) {
            return null;
        }
        $message = trim($message);

        return '' === $message ? null : $message;
    }
}
