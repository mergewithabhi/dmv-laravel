<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceProductionHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->isProduction() || $request->isSecure()) {
            return $next($request);
        }

        $applicationUrl = rtrim((string) config('app.url'), '/');
        abort_unless(
            str_starts_with($applicationUrl, 'https://'),
            500,
            'APP_URL must use HTTPS in production.'
        );

        return redirect()->to($applicationUrl.$request->getRequestUri(), 301);
    }
}
