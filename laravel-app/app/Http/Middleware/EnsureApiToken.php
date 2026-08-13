<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('services.ocr.auth_token');

        if (empty($token)) {
            return $next($request);
        }

        $provided = $request->bearerToken() ?? $request->header('X-Auth-Token');

        if (! is_string($provided) || ! hash_equals($token, $provided)) {
            abort(401, 'Unauthorized');
        }

        return $next($request);
    }
}