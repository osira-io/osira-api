<?php

declare(strict_types=1);

namespace App\Internationalization;

final class SupportedLocale
{
    public const string EN = 'en';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::EN];
    }

    public static function normalize(string $locale): string
    {
        return mb_strtolower(trim($locale));
    }

    public static function isSupported(string $locale): bool
    {
        return \in_array(self::normalize($locale), self::all(), true);
    }

    private function __construct()
    {
    }
}
