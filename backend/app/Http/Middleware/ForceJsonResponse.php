<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceJsonResponse
{
    // Forces the request to expect a JSON response before passing it to the next middleware or controller
    public function handle(Request $request, Closure $next): Response
    {
        // Sets the Accept header to application/json so Laravel returns JSON-formatted responses
        $request->headers->set('Accept', 'application/json');

        // Passes the modified request to the next step in the request lifecycle
        return $next($request);
    }
}
