<?php

declare(strict_types=1);

namespace App\Entity\Incident;

enum IncidentStatus: string
{
    case FIRING = 'firing';
    case RESOLVED = 'resolved';
}
