<?php

namespace Tests\Feature;

use App\Contracts\NewsletterProvider;
use App\Jobs\SyncNewsletterSubscriber;
use App\Models\NewsletterSubscriber;
use App\Services\Newsletter\BrevoNewsletterProvider;
use App\Services\Newsletter\MailchimpNewsletterProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class NewsletterProviderUnsubscribeTest extends TestCase
{
    use RefreshDatabase;

    public function test_mailchimp_unsubscribe_updates_the_remote_member(): void
    {
        config()->set('services.newsletter.mailchimp', [
            'api_key' => 'test-key',
            'list_id' => 'test-list',
            'data_center' => 'us1',
        ]);
        Http::fake(['*' => Http::response([], 200)]);
        $subscriber = $this->subscriber('mailchimp@example.com', 'member-id');

        app(MailchimpNewsletterProvider::class)->unsubscribe($subscriber);

        Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
            && $request->url() === 'https://us1.api.mailchimp.com/3.0/lists/test-list/members/member-id'
            && $request['status'] === 'unsubscribed');
    }

    public function test_brevo_unsubscribe_removes_the_remote_contact(): void
    {
        config()->set('services.newsletter.brevo.api_key', 'test-key');
        Http::fake(['*' => Http::response([], 204)]);
        $subscriber = $this->subscriber('brevo+fan@example.com');

        app(BrevoNewsletterProvider::class)->unsubscribe($subscriber);

        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
            && $request->url() === 'https://api.brevo.com/v3/contacts/brevo%2Bfan%40example.com');
    }

    public function test_delayed_subscribe_job_cannot_resubscribe_an_unsubscribed_contact(): void
    {
        $subscriber = $this->subscriber('stopped@example.com');
        $subscriber->forceFill([
            'status' => 'unsubscribed',
            'unsubscribed_at' => now(),
        ])->save();
        $provider = Mockery::mock(NewsletterProvider::class);
        $provider->shouldNotReceive('subscribe');

        (new SyncNewsletterSubscriber($subscriber))->handle($provider);

        $this->assertSame('unsubscribed', $subscriber->refresh()->status);
    }

    private function subscriber(
        string $email,
        ?string $providerId = null
    ): NewsletterSubscriber {
        return NewsletterSubscriber::query()->create([
            'email' => $email,
            'email_hash' => hash('sha256', strtolower($email)),
            'status' => 'subscribed',
            'provider' => 'log',
            'provider_id' => $providerId,
            'consent' => true,
            'subscribed_at' => now(),
        ]);
    }
}
