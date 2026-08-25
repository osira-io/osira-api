<?php

declare(strict_types=1);

namespace App\Entity\Alert;

enum AlertSeverity: string
{
    case INFO = 'info';
    case WARNING = 'warning';
    case CRITICAL = 'critical';
}
