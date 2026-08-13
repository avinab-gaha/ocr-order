<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiToken
{
    /**
     * Protect the API with a single shared bearer token stored in AUTH_TOKEN.
     * When AUTH_TOKEN is empty the check is disabled (convenient for local
     * development); in production always set it.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.orders.auth_token', '');

        if ($expected !== '' && ! hash_equals($expected, (string) $request->bearerToken())) {
            abort(401, 'Unauthorized');
        }

        return $next($request);
    }
}