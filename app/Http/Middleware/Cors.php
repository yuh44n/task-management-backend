<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Cors
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Handle preflight OPTIONS request
        if ($request->isMethod('OPTIONS')) {
            $response = response('', 200);
        } else {
            $response = $next($request);
        }

        // Add CORS headers to the response
        $origin = $request->header('Origin');
        $allowedOrigins = ['https://remarkable-dodol-6ce841.netlify.app', 'http://localhost:3001'];
        
        // Check if the origin is allowed
        if (in_array($origin, $allowedOrigins) || !$origin) {
            // Set the Access-Control-Allow-Origin header to the origin
            $response->headers->set('Access-Control-Allow-Origin', $origin ?: '*');
        } else {
            // If the origin is not in the allowed list, set to default
            $response->headers->set('Access-Control-Allow-Origin', 'https://remarkable-dodol-6ce841.netlify.app');
        }
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN, Accept');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Max-Age', '7200');

        return $response;
    }
}