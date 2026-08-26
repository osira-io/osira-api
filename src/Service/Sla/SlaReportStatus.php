<?php

declare(strict_types=1);

namespace App\Service\Sla;

enum SlaReportStatus: string
{
    case COMPLIANT = 'compliant';
    case NON_COMPLIANT = 'non_compliant';
    case NO_DATA = 'no_data';
}
