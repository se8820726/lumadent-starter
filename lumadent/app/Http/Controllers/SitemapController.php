<?php

namespace App\Http\Controllers;

use App\Content\ServiceCatalog;
use App\Support\LocalizedUrl;
use Illuminate\Http\Response;

final class SitemapController extends Controller
{
    public function __invoke(ServiceCatalog $services, LocalizedUrl $urls): Response
    {
        $pages = array_map(fn ($name) => [$name, []], [
            'home', 'services.index', 'dentists.index', 'about', 'contact', 'privacy', 'booking.demo',
        ]);

        foreach ($services->all() as $service) {
            $pages[] = ['services.show', ['service' => $service['slug']]];
        }

        $entries = [];
        foreach ($pages as [$name, $parameters]) {
            $alternates = [];
            foreach (array_keys(config('localization.locales')) as $locale) {
                $alternates[$locale] = $urls->route($name, $parameters, $locale);
            }
            $alternates['x-default'] = $alternates[config('localization.default')];
            foreach (array_keys(config('localization.locales')) as $locale) {
                $entries[] = ['url' => $alternates[$locale], 'alternates' => $alternates];
            }
        }

        return response()->view('sitemap', compact('entries'))->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
