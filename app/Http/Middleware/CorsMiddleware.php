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
        // Handle preflight OPTIONS request immediately
        if ($request->isMethod('OPTIONS')) {
            return response('', 204)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', '*')
                ->header('Access-Control-Max-Age', '86400');
        }

        // Process the request and add CORS headers to the response
        $response = $next($request);

        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', '*');
        $response->headers->set('Access-Control-Expose-Headers', 'Content-Disposition');

        return $response;
    }
}
