<?php

declare(strict_types=1);

namespace App\Service\Incident;

use App\Dto\Incident\IncidentActivityOutput;
use App\Dto\Incident\IncidentActorOutput;
use App\Entity\Incident\IncidentActivity;
use App\Entity\User\User;

final readonly class IncidentActivityOutputFactory
{
    public function create(IncidentActivity $activity): IncidentActivityOutput
    {
        return new IncidentActivityOutput(
            (string) $activity->id(),
            $activity->type()->value,
            $activity->message(),
            new IncidentActorOutput($activity->actorId(), $activity->actorEmail()),
            $activity->createdAt(),
        );
    }

    public function actor(User $actor): IncidentActorOutput
    {
        return new IncidentActorOutput((string) $actor->id(), $actor->getUserIdentifier());
    }
}
