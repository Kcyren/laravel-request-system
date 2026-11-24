<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laboratory 10 - request identifier.
 *
 * Gives every HTTP request an ID (reuses a safe incoming X-Request-Id or
 * creates a UUID). The ID is added to every log line written during the
 * request and returned in the X-Request-Id response header, so related
 * events (for example a request.created log and its HTTP response) can be
 * connected.
 */
class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = (string) $request->headers->get('X-Request-Id', '');
        $requestId = preg_match('/^[A-Za-z0-9\-]{8,64}$/', $incoming) ? $incoming : (string) Str::uuid();

        $request->attributes->set('request_id', $requestId);
        Log::withContext(['request_id' => $requestId]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
