<?php

declare(strict_types=1);

namespace App\Entity\Alert;

enum AlertOperator: string
{
    case GT = 'gt';
    case GTE = 'gte';
    case LT = 'lt';
    case LTE = 'lte';
    case EQ = 'eq';
    case NEQ = 'neq';

    public function inverse(): self
    {
        return match ($this) {
            self::GT => self::LTE,
            self::GTE => self::LT,
            self::LT => self::GTE,
            self::LTE => self::GT,
            self::EQ => self::NEQ,
            self::NEQ => self::EQ,
        };
    }
}
