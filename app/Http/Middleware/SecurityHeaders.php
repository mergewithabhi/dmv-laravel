<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $nonce = base64_encode(random_bytes(18));
        $turnstile = config('services.turnstile.enabled')
            ? ' https://challenges.cloudflare.com'
            : '';
        $instagramImages = ' https://*.cdninstagram.com https://*.fbcdn.net';

        if ($this->isHtmlResponse($response)) {
            $response->setContent(
                preg_replace(
                    '/<script(?![^>]*\bnonce=)(?=[\s>])/i',
                    '<script nonce="'.$nonce.'"',
                    (string) $response->getContent()
                )
            );
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; img-src 'self' data: blob:{$instagramImages}; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self' 'nonce-{$nonce}'{$turnstile}; script-src-attr 'none'; connect-src 'self'{$turnstile}; frame-src 'self'{$turnstile}; object-src 'none'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'"
        );

        if ($this->containsSensitiveContent($request)) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
            $response->headers->set('Surrogate-Control', 'no-store');
        }

        if (app()->isProduction() && $request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
    }

    private function isHtmlResponse(Response $response): bool
    {
        return Str::contains(
            strtolower((string) $response->headers->get('Content-Type')),
            'text/html'
        ) && is_string($response->getContent());
    }

    private function containsSensitiveContent(Request $request): bool
    {
        return $request->is([
            'admin',
            'admin/*',
            'login',
            'forgot-password',
            'reset-password',
            'reset-password/*',
            'email/verify',
            'email/verify/*',
            'two-factor-challenge',
            'user/*',
        ]);
    }
}
