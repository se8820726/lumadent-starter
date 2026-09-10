<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AddReleaseHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $releaseId = config('app.release_id');

        if (is_string($releaseId) && preg_match('/\A[a-f0-9]{40}\z/', $releaseId) === 1) {
            $response->headers->set('X-LumaDent-Release', $releaseId);
        }

        return $response;
    }
}
