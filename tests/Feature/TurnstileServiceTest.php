<?php

namespace Tests\Feature;

use App\Services\TurnstileService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TurnstileServiceTest extends TestCase
{
    public function test_turnstile_is_optional_until_enabled(): void
    {
        config()->set('services.turnstile.enabled', false);

        $this->assertTrue(app(TurnstileService::class)->verify(null, '127.0.0.1'));
    }

    public function test_enabled_turnstile_fails_closed_and_accepts_a_valid_token(): void
    {
        config()->set('services.turnstile', [
            'enabled' => true,
            'site_key' => 'site-key',
            'secret_key' => 'secret-key',
        ]);
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true]),
        ]);

        $service = app(TurnstileService::class);
        $this->assertFalse($service->verify('', '127.0.0.1'));
        $this->assertTrue($service->verify('valid-token', '127.0.0.1'));
        Http::assertSent(fn ($request) => $request['secret'] === 'secret-key'
            && $request['response'] === 'valid-token');
    }
}
