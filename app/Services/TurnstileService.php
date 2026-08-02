<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TurnstileService
{
    public function enabled(): bool
    {
        return (bool) config('services.turnstile.enabled');
    }

    public function verify(?string $token, ?string $ipAddress): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        $siteKey = config('services.turnstile.site_key');
        $secretKey = config('services.turnstile.secret_key');

        if (! filled($siteKey) || ! filled($secretKey)) {
            Log::critical('Turnstile is enabled but its credentials are incomplete.');

            return false;
        }

        if (! filled($token) || strlen($token) > 2048) {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(8)
                ->retry(2, 200)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => $secretKey,
                    'response' => $token,
                    'remoteip' => $ipAddress,
                ]);

            if (! $response->successful() || $response->json('success') !== true) {
                return false;
            }

            $expectedHostname = config('services.turnstile.hostname');
            $expectedAction = config('services.turnstile.action');

            return (! filled($expectedHostname) || hash_equals(
                strtolower((string) $expectedHostname),
                strtolower((string) $response->json('hostname'))
            )) && (! filled($expectedAction) || hash_equals(
                (string) $expectedAction,
                (string) $response->json('action')
            ));
        } catch (\Throwable $exception) {
            Log::warning('Turnstile verification failed.', [
                'exception' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
