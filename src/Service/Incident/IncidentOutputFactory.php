<?php

declare(strict_types=1);

namespace App\Service\Incident;

use App\Dto\Incident\IncidentOutput;
use App\Entity\Incident\Incident;

final readonly class IncidentOutputFactory
{
    public function __construct(private IncidentActivityOutputFactory $activityOutputFactory)
    {
    }

    public function create(Incident $incident): IncidentOutput
    {
        return new IncidentOutput(
            (string) $incident->id(),
            (string) $incident->node()->id(),
            $incident->node()->hostname(),
            (string) $incident->alertRule()->id(),
            $incident->alertRule()->name(),
            $incident->status()->value,
            $incident->severity()->value,
            $incident->title(),
            $incident->message(),
            $incident->labels(),
            $incident->firstTriggeredAt(),
            $incident->lastTriggeredAt(),
            $incident->resolvedAt(),
            $incident->acknowledgedAt(),
            null !== $incident->acknowledgedBy() ? $this->activityOutputFactory->actor($incident->acknowledgedBy()) : null,
            $incident->lastValue(),
            $incident->occurrences(),
            $incident->createdAt(),
            $incident->updatedAt(),
        );
    }
}
