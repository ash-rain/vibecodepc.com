<?php

declare(strict_types=1);

namespace VibecodePC\Common\Enums;

enum ProjectFramework: string
{
    case Laravel = 'laravel';
    case NextJs = 'nextjs';
    case Astro = 'astro';
    case FastApi = 'fastapi';
    case StaticHtml = 'static-html';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Laravel => 'Laravel',
            self::NextJs => 'Next.js',
            self::Astro => 'Astro',
            self::FastApi => 'FastAPI',
            self::StaticHtml => 'Static HTML',
            self::Custom => 'Custom',
        };
    }

    public function defaultPort(): int
    {
        return match ($this) {
            self::Laravel => 8000,
            self::NextJs => 3000,
            self::Astro => 4321,
            self::FastApi => 8001,
            self::StaticHtml => 8080,
            self::Custom => 8082,
        };
    }
}
