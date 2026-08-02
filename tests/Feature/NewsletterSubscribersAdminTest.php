<?php

namespace Tests\Feature;

use App\Jobs\SyncNewsletterSubscriber;
use App\Jobs\UnsubscribeNewsletterSubscriber;
use App\Livewire\Admin\NewsletterSubscribers;
use App\Models\NewsletterSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Concerns\CreatesCmsUsers;
use Tests\TestCase;

class NewsletterSubscribersAdminTest extends TestCase
{
    use CreatesCmsUsers, RefreshDatabase;

    public function test_route_and_component_require_submission_management_permission(): void
    {
        $editor = $this->enableTwoFactor($this->cmsUser(['manage pages'], 'Newsletter Editor'));

        $this->actingAs($editor)
            ->get(route('admin.newsletter-subscribers'))
            ->assertForbidden();

        Livewire::actingAs($editor)
            ->test(NewsletterSubscribers::class)
            ->assertForbidden();

        $manager = $this->enableTwoFactor(
            $this->cmsUser(['manage submissions'], 'Newsletter Manager')
        );

        $this->actingAs($manager)
            ->get(route('admin.newsletter-subscribers'))
            ->assertOk()
            ->assertSee('Newsletter subscribers');
    }

    public function test_manager_can_search_filter_sort_and_paginate_subscribers(): void
    {
        $manager = $this->cmsUser(['manage submissions'], 'Newsletter Manager');
        $target = $this->subscriber('target@example.com', [
            'status' => 'subscribed',
            'provider' => 'mailchimp',
            'provider_id' => 'provider-target',
            'last_synced_at' => now(),
        ]);
        $this->subscriber('failed@example.com', [
            'status' => 'pending',
            'last_error' => 'Provider timeout',
        ]);

        foreach (range(1, 14) as $index) {
            $this->subscriber("subscriber{$index}@example.com");
        }

        Livewire::actingAs($manager)
            ->test(NewsletterSubscribers::class)
            ->assertSee('16 subscribers')
            ->set('search', 'target@example.com')
            ->assertSee($target->email)
            ->assertDontSee('failed@example.com')
            ->call('resetFilters')
            ->set('statusFilter', 'failed')
            ->assertSee('failed@example.com')
            ->assertDontSee($target->email)
            ->call('resetFilters')
            ->call('sortBy', 'provider')
            ->assertSet('sortField', 'provider')
            ->assertSet('sortDirection', 'asc')
            ->set('perPage', 10)
            ->assertSee('Page 1 of 2')
            ->call('nextPage')
            ->assertSet('paginators.page', 2);
    }

    public function test_manager_can_unsubscribe_retry_and_delete(): void
    {
        Queue::fake();
        $manager = $this->cmsUser(['manage submissions'], 'Newsletter Manager');
        $subscriber = $this->subscriber('actions@example.com', [
            'status' => 'subscribed',
            'consent' => true,
            'last_error' => 'Previous provider error',
        ]);

        Livewire::actingAs($manager)
            ->test(NewsletterSubscribers::class)
            ->call('retrySync', $subscriber->id)
            ->assertHasNoErrors();

        $subscriber->refresh();
        $this->assertSame('pending', $subscriber->status);
        $this->assertNull($subscriber->last_error);
        Queue::assertPushed(
            SyncNewsletterSubscriber::class,
            fn (SyncNewsletterSubscriber $job) => $job->subscriber->is($subscriber)
        );

        Livewire::actingAs($manager)
            ->test(NewsletterSubscribers::class)
            ->call('unsubscribe', $subscriber->id)
            ->assertHasNoErrors();

        $subscriber->refresh();
        $this->assertSame('unsubscribed', $subscriber->status);
        $this->assertNotNull($subscriber->unsubscribed_at);
        $this->assertTrue($subscriber->consent);
        Queue::assertPushed(UnsubscribeNewsletterSubscriber::class);

        Livewire::actingAs($manager)
            ->test(NewsletterSubscribers::class)
            ->call('retrySync', $subscriber->id)
            ->assertStatus(422);

        Queue::assertPushed(SyncNewsletterSubscriber::class, 1);

        Livewire::actingAs($manager)
            ->test(NewsletterSubscribers::class)
            ->call('delete', $subscriber->id)
            ->assertStatus(422);

        $subscriber->forceFill([
            'last_synced_at' => $subscriber->unsubscribed_at,
            'last_error' => null,
        ])->save();

        Livewire::actingAs($manager)
            ->test(NewsletterSubscribers::class)
            ->call('delete', $subscriber->id)
            ->assertHasNoErrors();

        $this->assertModelMissing($subscriber);
    }

    public function test_csv_export_requires_export_permission_and_downloads_filtered_rows(): void
    {
        $manager = $this->cmsUser(['manage submissions'], 'Newsletter Manager');
        $exporter = $this->cmsUser(
            ['manage submissions', 'export submissions'],
            'Newsletter Exporter'
        );
        $this->subscriber('included@example.com', [
            'status' => 'subscribed',
            'provider_id' => '=spreadsheet-formula',
        ]);
        $this->subscriber('excluded@example.com', ['status' => 'unsubscribed']);
        Livewire::actingAs($manager)
            ->test(NewsletterSubscribers::class)
            ->call('export')
            ->assertForbidden();

        $download = Livewire::actingAs($exporter)
            ->test(NewsletterSubscribers::class)
            ->set('statusFilter', 'subscribed')
            ->call('export')
            ->assertFileDownloaded();

        $csv = base64_decode(data_get($download->effects, 'download.content'));
        $this->assertMatchesRegularExpression(
            '/^dmv-newsletter-subscribers-\d{4}-\d{2}-\d{2}-\d{6}\.csv$/',
            data_get($download->effects, 'download.name')
        );
        $this->assertStringContainsString('included@example.com', $csv);
        $this->assertStringContainsString("'=spreadsheet-formula", $csv);
        $this->assertStringNotContainsString('excluded@example.com', $csv);
    }

    private function subscriber(string $email, array $attributes = []): NewsletterSubscriber
    {
        $email = strtolower($email);

        return NewsletterSubscriber::query()->create(array_merge([
            'email' => $email,
            'email_hash' => hash('sha256', $email),
            'status' => 'pending',
            'provider' => 'log',
            'consent' => true,
            'subscribed_at' => now(),
        ], $attributes));
    }
}
