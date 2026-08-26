<?php

declare(strict_types=1);

namespace App\Entity\Alert;

enum AlertSeverity: string
{
    public const array VALUES = ['info', 'warning', 'critical'];

    case INFO = 'info';
    case WARNING = 'warning';
    case CRITICAL = 'critical';
}
