<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

final class LocalizedUrl
{
    public function route(string $name, array $parameters = [], ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $prefix = $locale === config('localization.default') ? '' : $locale.'.';

        return route($prefix.$name, $parameters);
    }

    public function pageName(): ?string
    {
        $name = Route::currentRouteName();

        foreach (array_keys(config('localization.locales')) as $locale) {
            if ($name && str_starts_with($name, $locale.'.')) {
                $name = substr($name, strlen($locale) + 1);
                break;
            }
        }

        return in_array($name, ['home', 'services.index', 'services.show', 'dentists.index', 'about', 'contact', 'privacy', 'booking.demo'], true)
            ? $name : null;
    }

    public function current(?string $locale = null): string
    {
        $name = $this->pageName();

        return $this->route($name ?? 'home', $name ? $this->parameters() : [], $locale);
    }

    public function parameters(): array
    {
        $route = Route::current();

        return array_intersect_key($route->parameters(), array_flip($route->parameterNames()));
    }
}
