<?php

namespace App\Http\Middleware;

use App\Support\LocalizedUrl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RedirectDefaultLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $name = substr($request->route()->getName(), strlen('default-alias.'));
        $urls = app(LocalizedUrl::class);
        $url = $urls->route($name, $urls->parameters(), config('localization.default'));
        $query = $request->getQueryString();

        return redirect()->to($url.($query ? '?'.$query : ''), 301);
    }
}
