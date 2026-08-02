<?php

namespace Tests\Feature;

use App\Jobs\SyncNewsletterSubscriber;
use App\Livewire\Site\SitePage;
use App\Models\FormSubmission;
use App\Models\SiteSetting;
use App\Notifications\SubmissionReceived;
use App\Services\NewsletterService;
use App\Services\StaticSiteImporter;
use App\Services\SubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class SiteFormsTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_form_saves_encrypted_data_and_uses_cms_success_copy(): void
    {
        app(StaticSiteImporter::class)->run();
        Notification::fake();
        SiteSetting::setValue('forms.contact_success', 'Custom contact confirmation.', 'Contact success');

        Livewire::test(SitePage::class, ['slug' => 'contact'])
            ->set('contact.name', 'Alex Johnson')
            ->set('contact.email', 'alex@gmail.com')
            ->set('contact.phone', '301-555-0100')
            ->set('contact.subject', 'Tickets')
            ->set('contact.message', 'Please contact me.')
            ->set('contactConsent', true)
            ->call('submitContact')
            ->assertHasNoErrors()
            ->assertSet('contactMessage', 'Custom contact confirmation.')
            ->assertSet('contactConsent', false);

        $submission = FormSubmission::query()->firstOrFail();
        $this->assertSame('alex@gmail.com', $submission->email);
        $this->assertSame('Please contact me.', $submission->payload['message']);
        $this->assertTrue($submission->consent);
        $raw = DB::table('form_submissions')->where('id', $submission->id)->first();
        $this->assertStringNotContainsString('alex@gmail.com', $raw->email);
        $this->assertStringNotContainsString('Please contact me.', $raw->payload);
        Notification::assertSentOnDemand(SubmissionReceived::class);
    }

    public function test_newsletter_form_is_honeypot_protected_and_dispatches_provider_sync(): void
    {
        app(StaticSiteImporter::class)->run();
        Queue::fake();

        Livewire::test(SitePage::class, ['slug' => 'home'])
            ->set('newsletterEmail', 'fan@gmail.com')
            ->set('newsletterConsent', true)
            ->set('website', 'https://spam.invalid')
            ->call('submitNewsletter')
            ->assertHasErrors('website');

        Livewire::test(SitePage::class, ['slug' => 'home'])
            ->set('newsletterEmail', 'fan@gmail.com')
            ->set('newsletterConsent', true)
            ->call('submitNewsletter')
            ->assertHasNoErrors()
            ->assertSet('newsletterEmail', '')
            ->assertSet('newsletterConsent', false);

        Queue::assertPushed(SyncNewsletterSubscriber::class);
    }

    public function test_each_public_form_requires_explicit_consent_and_renders_its_error(): void
    {
        app(StaticSiteImporter::class)->run();

        Livewire::test(SitePage::class, ['slug' => 'home'])
            ->set('newsletterEmail', 'fan@gmail.com')
            ->call('submitNewsletter')
            ->assertHasErrors(['newsletterConsent' => 'accepted'])
            ->assertSee('Please confirm your consent.');

        Livewire::test(SitePage::class, ['slug' => 'contact'])
            ->set('contact.name', 'Alex Johnson')
            ->set('contact.email', 'alex@gmail.com')
            ->set('contact.subject', 'Tickets')
            ->set('contact.message', 'Please contact me.')
            ->call('submitContact')
            ->assertHasErrors(['contactConsent' => 'accepted'])
            ->assertSee('Please confirm your consent.');

        Livewire::test(SitePage::class, ['slug' => 'sponsors'])
            ->set('sponsor.name', 'Alex Johnson')
            ->set('sponsor.company', 'Test Company')
            ->set('sponsor.email', 'alex@gmail.com')
            ->set('sponsor.phone', '301-555-0100')
            ->set('sponsor.level', 'Gold Sponsor')
            ->call('submitSponsor')
            ->assertHasErrors(['sponsorConsent' => 'accepted'])
            ->assertSee('Please confirm your consent.');

        $this->assertSame(0, FormSubmission::query()->count());
    }

    public function test_services_never_grant_missing_consent(): void
    {
        Queue::fake();

        try {
            app(NewsletterService::class)->subscribe('fan@gmail.com');
            $this->fail('Newsletter subscription accepted missing consent.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('consent', $exception->errors());
        }

        try {
            app(SubmissionService::class)->create(
                'contact',
                ['name' => 'Alex', 'email' => 'alex@gmail.com'],
                request()
            );
            $this->fail('Contact submission accepted missing consent.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('consent', $exception->errors());
        }

        $this->assertSame(0, FormSubmission::query()->count());
    }
}
