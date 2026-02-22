<?php

namespace App;

enum SupportedLocale: string
{
    case English = 'en';
    case French = 'fr';
    case German = 'de';
    case Spanish = 'es';
    case Italian = 'it';
    case Portuguese = 'pt';
    case Dutch = 'nl';
    case Bulgarian = 'bg';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function routePattern(): string
    {
        $nonDefault = collect(self::cases())
            ->filter(fn (self $locale) => $locale !== self::English)
            ->map(fn (self $locale) => $locale->value)
            ->implode('|');

        return $nonDefault;
    }
}
