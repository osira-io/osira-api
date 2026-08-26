<?php

declare(strict_types=1);

namespace App\Entity\Alert;

enum AlertRuleImpactType: string
{
    public const array VALUES = ['availability', 'performance', 'informational'];

    case AVAILABILITY = 'availability';
    case PERFORMANCE = 'performance';
    case INFORMATIONAL = 'informational';
}
