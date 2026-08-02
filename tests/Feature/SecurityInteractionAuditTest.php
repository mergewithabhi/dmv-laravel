<?php

namespace Tests\Feature;

use App\Jobs\SyncNewsletterSubscriber;
use App\Livewire\Site\SitePage;
use App\Models\FormSubmission;
use App\Models\NewsletterSubscriber;
use App\Models\Page;
use App\Models\User;
use App\Notifications\SubmissionReceived;
use App\Services\StaticSiteImporter;
use App\Services\TurnstileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use RuntimeException;
use Tests\Concerns\CreatesCmsUsers;
use Tests\TestCase;

class SecurityInteractionAuditTest extends TestCase
{
    use CreatesCmsUsers, RefreshDatabase;

    public function test_authentication_logout_verification_and_two_factor_modes_work(): void
    {
        $password = 'Secure-Admin-Password-2026!';
        $user = User::factory()->create([
            'password' => Hash::make($password),
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => $password])
            ->assertRedirect('/admin');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();

        $unverified = $this->cmsUser(['manage pages'], 'Verification Editor');
        $unverified->forceFill(['email_verified_at' => null])->save();
        config(['cms.security.require_two_factor' => false]);

        $this->actingAs($unverified)
            ->get('/admin/pages')
            ->assertRedirect(route('verification.notice'));

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(10),
            ['id' => $unverified->id, 'hash' => sha1($unverified->email)]
        );
        $this->get($verificationUrl)->assertRedirect('/admin/pages');
        $this->assertNotNull($unverified->fresh()->email_verified_at);

        auth()->logout();
        $twoFactorUser = User::factory()->create([
            'password' => Hash::make($password),
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code'])),
            'two_factor_confirmed_at' => now(),
        ]);

        $this->post('/login', [
            'email' => $twoFactorUser->email,
            'password' => $password,
        ])->assertRedirect(route('two-factor.login'));
        $this->assertGuest();
        $this->post(route('two-factor.cancel'))->assertRedirect(route('login'));
        $this->assertNull(session('login.id'));

        $administrator = $this->cmsUser(['manage pages'], 'Two Factor Editor');
        config(['cms.security.require_two_factor' => true]);
        $this->actingAs($administrator)
            ->get('/admin/pages')
            ->assertRedirect(route('admin.security'));

        config(['cms.security.require_two_factor' => false]);
        $this->get('/admin/pages')->assertOk();
    }

    public function test_password_resets_and_updates_revoke_other_sessions(): void
    {
        config(['session.driver' => 'database']);
        $oldPassword = 'Old-Secure-Password-2026!';
        $newPassword = 'New-Secure-Password-2026!';
        $user = User::factory()->create([
            'password' => Hash::make($oldPassword),
            'remember_token' => 'old-remember-token',
        ]);
        $this->insertSession('reset-stale-session', $user);
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertTrue(Hash::check($newPassword, $user->password));
        $this->assertNotSame('old-remember-token', $user->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'reset-stale-session']);

        $this->insertSession('update-stale-session', $user);
        $this->actingAs($user)
            ->put('/user/password', [
                'current_password' => $newPassword,
                'password' => $oldPassword,
                'password_confirmation' => $oldPassword,
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check($oldPassword, $user->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'update-stale-session']);
    }

    public function test_login_is_throttled_and_state_changing_routes_require_csrf_in_runtime(): void
    {
        $email = 'rate-limit-'.str()->random(10).'@example.test';

        foreach (range(1, 5) as $attempt) {
            $this->post('/login', ['email' => $email, 'password' => 'wrong-password'])
                ->assertSessionHasErrors('email');
        }

        $this->post('/login', ['email' => $email, 'password' => 'wrong-password'])
            ->assertTooManyRequests();

        $originalEnvironment = $this->app->environment();
        $this->app['env'] = 'local';

        try {
            $this->post('/logout')->assertStatus(419);
        } finally {
            $this->app['env'] = $originalEnvironment;
        }
    }

    public function test_csp_uses_script_nonces_and_livewire_csp_runtime_without_eval(): void
    {
        $response = $this->get('/login')->assertOk();
        $policy = (string) $response->headers->get('Content-Security-Policy');

        $this->assertTrue(config('livewire.csp_safe'));
        $this->assertStringNotContainsString("'unsafe-eval'", $policy);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $policy);
        $this->assertStringContainsString("script-src-attr 'none'", $policy);
        $this->assertStringNotContainsString("'unsafe-hashes'", $policy);
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[^']+'/i", $policy);
        preg_match("/script-src 'self' 'nonce-([^']+)'/i", $policy, $matches);
        $this->assertNotEmpty($matches[1] ?? null);
        $response->assertSee('nonce="'.$matches[1].'"', false);
        $response->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_turnstile_fails_closed_and_validates_configured_context(): void
    {
        config(['services.turnstile' => [
            'enabled' => true,
            'site_key' => null,
            'secret_key' => null,
            'hostname' => null,
            'action' => null,
        ]]);
        $this->assertFalse(app(TurnstileService::class)->verify('token', '127.0.0.1'));

        config(['services.turnstile' => [
            'enabled' => true,
            'site_key' => 'site-key',
            'secret_key' => 'secret-key',
            'hostname' => 'www.dmvwarriors.test',
            'action' => 'contact',
        ]]);
        Http::fakeSequence()
            ->push([
                'success' => true,
                'hostname' => 'attacker.test',
                'action' => 'contact',
            ])
            ->push([
                'success' => true,
                'hostname' => 'www.dmvwarriors.test',
                'action' => 'contact',
            ]);

        $service = app(TurnstileService::class);
        $this->assertFalse($service->verify('wrong-context', '127.0.0.1'));
        $this->assertTrue($service->verify('valid-context', '127.0.0.1'));
    }

    public function test_admin_authorization_and_signed_previews_are_enforced(): void
    {
        config(['cms.security.require_two_factor' => false]);
        $page = Page::query()->create([
            'slug' => 'home',
            'template_key' => 'home',
            'title' => 'Home',
            'status' => 'draft',
            'workflow_status' => 'draft',
            'is_indexable' => false,
        ]);
        $previewUrl = URL::temporarySignedRoute(
            'admin.pages.preview',
            now()->addMinutes(10),
            $page
        );

        $this->get('/admin/pages')->assertRedirect(route('login'));

        $ordinaryUser = User::factory()->create();
        $this->actingAs($ordinaryUser)->get($previewUrl)->assertForbidden();

        $editor = $this->cmsUser(['manage pages'], 'Preview Editor');
        $this->actingAs($editor)
            ->get(route('admin.pages.preview', $page))
            ->assertForbidden();
        $this->get($previewUrl)->assertOk()->assertSee('Draft preview');

        $expiredUrl = URL::temporarySignedRoute(
            'admin.pages.preview',
            now()->subMinute(),
            $page
        );
        $this->get($expiredUrl)->assertForbidden();
    }

    public function test_downloads_and_public_runtime_routes_are_available_and_hardened(): void
    {
        $this->get('/schedule/calendar.ics')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->assertSee('BEGIN:VCALENDAR');

        $pack = $this->get('/sponsor-pack')->assertOk();
        $pack->assertHeader('Content-Type', 'application/pdf');
        $pack->assertHeader(
            'Content-Disposition',
            'attachment; filename=dmv-warriors-sponsor-pack.pdf'
        );
        $this->assertStringContainsString(
            'no-store',
            (string) $pack->headers->get('Cache-Control')
        );

        $this->get('/schedule/calendar/not/a-season.ics')->assertNotFound();
        $this->get('/definitely-not-a-route')->assertNotFound();
    }

    public function test_static_import_falls_back_to_public_assets_and_all_forms_deliver(): void
    {
        config(['cms.static_import_asset_sources' => [
            base_path('legacy-static/assets'),
            public_path('assets'),
        ]]);
        Notification::fake();
        Queue::fake();

        $counts = app(StaticSiteImporter::class)->run();
        $this->assertSame(6, $counts['pages']);
        $this->assertGreaterThan(0, $counts['media']);

        Livewire::test(SitePage::class, ['slug' => 'contact'])
            ->set('contact.name', 'Runtime Contact')
            ->set('contact.email', 'runtime.contact@gmail.com')
            ->set('contact.subject', 'Tickets')
            ->set('contact.message', 'Please contact me.')
            ->set('contactConsent', true)
            ->call('submitContact')
            ->assertHasNoErrors();

        Livewire::test(SitePage::class, ['slug' => 'sponsors'])
            ->set('sponsor.name', 'Runtime Sponsor')
            ->set('sponsor.company', 'Runtime Company')
            ->set('sponsor.email', 'runtime.sponsor@gmail.com')
            ->set('sponsor.phone', '301-555-0100')
            ->set('sponsor.level', 'Gold')
            ->set('sponsor.message', 'Please send partnership details.')
            ->set('sponsorConsent', true)
            ->call('submitSponsor')
            ->assertHasNoErrors();

        Livewire::test(SitePage::class, ['slug' => 'home'])
            ->set('newsletterEmail', 'runtime.newsletter@gmail.com')
            ->set('newsletterConsent', true)
            ->call('submitNewsletter')
            ->assertHasNoErrors();

        $this->assertSame(2, FormSubmission::query()->count());
        $this->assertSame(1, NewsletterSubscriber::query()->count());
        Notification::assertSentOnDemand(SubmissionReceived::class);
        Queue::assertPushed(SyncNewsletterSubscriber::class);

        foreach (['/', '/about', '/roster', '/schedule', '/sponsors', '/contact', '/news'] as $uri) {
            $this->get($uri)->assertOk();
        }
    }

    public function test_static_import_reports_all_invalid_asset_sources(): void
    {
        config(['cms.static_import_asset_sources' => [
            base_path('missing-legacy-assets'),
            public_path('missing-public-assets'),
        ]]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Static media import could not find a readable asset source.');

        app(StaticSiteImporter::class)->run();
    }

    private function insertSession(string $id, User $user): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Security audit',
            'payload' => '',
            'last_activity' => time(),
        ]);
    }
}
