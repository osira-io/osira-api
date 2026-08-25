<?php

declare(strict_types=1);

namespace App\Entity\Incident;

enum IncidentActivityType: string
{
    case ACKNOWLEDGED = 'acknowledged';
    case COMMENT = 'comment';
}
