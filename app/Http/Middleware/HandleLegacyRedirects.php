<?php

namespace App\Http\Middleware;

use App\Models\Redirect;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class HandleLegacyRedirects
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $path = '/'.ltrim($request->path(), '/');
        $configured = config('cms.legacy_redirects.'.$path);

        if ($configured) {
            return redirect($configured, 301);
        }

        if (Schema::hasTable('redirects')) {
            $redirect = Redirect::query()
                ->where('source_path', $path)
                ->where('is_enabled', true)
                ->first();

            if ($redirect) {
                $redirect->increment('hit_count');
                $redirect->forceFill(['last_hit_at' => now()])->saveQuietly();

                return redirect($redirect->destination_url, $redirect->status_code);
            }
        }

        return $next($request);
    }
}
