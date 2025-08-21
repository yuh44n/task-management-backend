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
            
            // Set CORS headers
            if ($origin) {
                // Allow the specific origin that made the request
                $response->header('Access-Control-Allow-Origin', $origin);
            } else {
                $response->header('Access-Control-Allow-Origin', '*');
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