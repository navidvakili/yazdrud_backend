<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsMiddleware
{
    /**
     * Handle an incoming request — adds CORS headers to every response
     * and handles OPTIONS preflight requests immediately.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->headers->get('Origin');
        $allowedHeaders = $request->headers->get('Access-Control-Request-Headers')
            ?? 'Content-Type, Authorization, Accept, X-Requested-With, Origin';

        $buildCorsResponse = function ($response) use ($origin, $allowedHeaders) {
            if ($origin) {
                $response->headers->set('Access-Control-Allow-Origin', $origin);
                $response->headers->set('Vary', 'Origin');
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
            } else {
                $response->headers->set('Access-Control-Allow-Origin', '*');
            }

            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', $allowedHeaders);
            $response->headers->set('Access-Control-Expose-Headers', 'Content-Disposition');
            $response->headers->set('Access-Control-Max-Age', '86400');

            return $response;
        };

        if ($request->isMethod('OPTIONS')) {
            return $buildCorsResponse(response('', 204));
        }

        $response = $next($request);

        return $buildCorsResponse($response);
    }
}
