<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        // Read the preferred language from the request header.
        $locale = $request->header('Accept-Language');

        // Use English only when it is explicitly requested; otherwise the default language is Portuguese.
        App::setLocale($locale === 'en' ? 'en' : 'pt');

        // Continue processing the request with the selected locale.
        return $next($request);
    }
}
