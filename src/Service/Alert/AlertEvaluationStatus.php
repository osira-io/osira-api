<?php

declare(strict_types=1);

namespace App\Service\Alert;

enum AlertEvaluationStatus: string
{
    case FIRING = 'firing';
    case OK = 'ok';
    case NO_DATA = 'no_data';
    case ERROR = 'error';
}
