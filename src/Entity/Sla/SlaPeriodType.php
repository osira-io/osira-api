<?php

declare(strict_types=1);

namespace App\Entity\Sla;

enum SlaPeriodType: string
{
    public const array VALUES = ['rolling_24_hours', 'rolling_7_days', 'rolling_30_days', 'current_month'];

    case ROLLING_24_HOURS = 'rolling_24_hours';
    case ROLLING_7_DAYS = 'rolling_7_days';
    case ROLLING_30_DAYS = 'rolling_30_days';
    case CURRENT_MONTH = 'current_month';
}
