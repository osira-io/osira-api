<?php

declare(strict_types=1);

namespace App\Dto\Incident;

final readonly class IncidentActorOutput
{
    public function __construct(public string $id, public string $email)
    {
    }
}
