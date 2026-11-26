<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignApiRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('api', 'api/*')) {
            return $next($request);
        }

        $requestId = (string) Str::uuid();
        $previousRequestId = Context::get('request_id');
        $request->attributes->set('request_id', $requestId);
        Context::add('request_id', $requestId);

        try {
            $response = $next($request);
            $response->headers->set('X-Request-Id', $requestId);

            return $response;
        } finally {
            if ($previousRequestId === null) {
                Context::forget('request_id');
            } else {
                Context::add('request_id', $previousRequestId);
            }
        }
    }
}
