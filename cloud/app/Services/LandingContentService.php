<?php

declare(strict_types=1);

namespace App\Services;

use App\SupportedLocale;
use Symfony\Component\Yaml\Yaml;

class LandingContentService
{
    private array $cache = [];

    public function load(string $locale = 'en'): array
    {
        if (isset($this->cache[$locale])) {
            return $this->cache[$locale];
        }

        if (SupportedLocale::tryFrom($locale) === null) {
            $locale = SupportedLocale::English->value;
        }

        $path = resource_path("content/{$locale}/landing.md");

        if (! file_exists($path)) {
            $path = resource_path('content/en/landing.md');
        }

        $raw = file_get_contents($path);

        if (preg_match('/\A---\n(.*?)\n---/s', $raw, $matches)) {
            $data = Yaml::parse($matches[1]);
        } else {
            $data = [];
        }

        $this->cache[$locale] = $data;

        return $data;
    }
}
