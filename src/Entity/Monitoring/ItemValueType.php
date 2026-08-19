<?php

declare(strict_types=1);

namespace App\Entity\Monitoring;

enum ItemValueType: string
{
    case FLOAT = 'float';
    case INTEGER = 'integer';
    case BOOLEAN = 'boolean';
    case STRING = 'string';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
