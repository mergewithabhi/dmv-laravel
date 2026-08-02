<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesCmsUsers;
use Tests\TestCase;

class AuthenticationSecurityTest extends TestCase
{
    use CreatesCmsUsers, RefreshDatabase;

    public function test_password_reset_response_does_not_reveal_account_existence(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'known@example.test']);

        $knownResponse = $this->postJson('/forgot-password', [
            'email' => $user->email,
        ]);
        $unknownResponse = $this->postJson('/forgot-password', [
            'email' => 'unknown@example.test',
        ]);

        $knownResponse->assertOk();
        $unknownResponse->assertOk();
        $this->assertSame($knownResponse->json(), $unknownResponse->json());
        $this->assertSame(
            ['message' => trans('passwords.sent')],
            $unknownResponse->json()
        );
        Notification::assertSentTo($user, ResetPassword::class);
        $this->assertSame(1, DB::table('password_reset_tokens')->count());
    }

    public function test_admin_without_confirmed_two_factor_is_redirected_to_security_setup(): void
    {
        config(['cms.security.require_two_factor' => true]);
        $administrator = $this->cmsUser(['manage pages'], 'Editor');

        $this->actingAs($administrator)
            ->get('/admin/pages')
            ->assertRedirect(route('admin.security'));
    }

    public function test_admin_can_access_cms_without_two_factor_when_requirement_is_disabled(): void
    {
        config(['cms.security.require_two_factor' => false]);
        $administrator = $this->cmsUser(['manage pages'], 'Editor');

        $this->actingAs($administrator)
            ->get('/admin/pages')
            ->assertOk();
    }

    public function test_confirmed_two_factor_allows_authorized_admin_access(): void
    {
        $administrator = $this->enableTwoFactor(
            $this->cmsUser(['manage pages'], 'Editor')
        );

        $this->actingAs($administrator)
            ->get('/admin/pages')
            ->assertOk();
    }

    public function test_security_setup_requires_recent_password_confirmation(): void
    {
        $administrator = $this->cmsUser([], 'Admin');

        $this->actingAs($administrator)
            ->get('/admin/security')
            ->assertRedirect(route('password.confirm'));

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->get('/admin/security')
            ->assertOk();
    }

    public function test_production_http_redirects_to_https_and_secure_responses_send_hsts(): void
    {
        $this->app['env'] = 'production';
        config(['app.url' => 'https://www.dmvwarriors.test']);

        $this->get('/login')
            ->assertRedirect('https://www.dmvwarriors.test/login')
            ->assertStatus(301);

        $this->get('https://www.dmvwarriors.test/login')
            ->assertOk()
            ->assertHeader(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
    }
}
