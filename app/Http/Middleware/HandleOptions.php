<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class HandleOptions
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
        // If this is an OPTIONS request, return a 200 response immediately
        if ($request->isMethod('OPTIONS')) {
            $response = response('', 200);
            
            // Get the origin from the request
            $origin = $request->header('Origin');
            $allowedOrigins = ['https://remarkable-dodol-6ce841.netlify.app', 'http://localhost:3001'];
            
            // Check if the origin is allowed
            if (in_array($origin, $allowedOrigins) || !$origin) {
                // Set the Access-Control-Allow-Origin header to the origin
                $response->header('Access-Control-Allow-Origin', $origin ?: '*');
            } else {
                // If the origin is not in the allowed list, set to default
                $response->header('Access-Control-Allow-Origin', 'https://remarkable-dodol-6ce841.netlify.app');
            }
            
            $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
            $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN, Accept');
            $response->header('Access-Control-Allow-Credentials', 'true');
            $response->header('Access-Control-Max-Age', '7200');
            
            return $response;
        }
        
        return $next($request);
    }
}