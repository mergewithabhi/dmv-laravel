<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('cms.security.require_two_factor', true)) {
            return $next($request);
        }

        if (! $request->user()?->hasEnabledTwoFactorAuthentication()) {
            return redirect()
                ->route('admin.security')
                ->with('success', 'Set up two-factor authentication before using the CMS.');
        }

        return $next($request);
    }
}
