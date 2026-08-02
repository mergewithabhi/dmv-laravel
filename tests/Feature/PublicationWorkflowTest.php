<?php

namespace Tests\Feature;

use App\Enums\PublicationStatus;
use App\Models\FormSubmission;
use App\Models\Page;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class PublicationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_scheduled_content_is_published_but_future_content_is_not(): void
    {
        $publisher = User::factory()->create();
        $due = Person::query()->create([
            'type' => 'player',
            'first_name' => 'Due',
            'last_name' => 'Player',
            'display_name' => 'Due Player',
            'slug' => 'due',
            'status' => PublicationStatus::Draft,
        ]);
        $future = Person::query()->create([
            'type' => 'player',
            'first_name' => 'Future',
            'last_name' => 'Player',
            'display_name' => 'Future Player',
            'slug' => 'future',
            'status' => PublicationStatus::Draft,
        ]);

        $due->approve($publisher, now()->addMinute());
        $future->approve($publisher, now()->addHour());
        $this->travel(2)->minutes();

        $this->artisan('cms:publish-scheduled')
            ->expectsOutput('Published 1 scheduled record(s).')
            ->assertSuccessful();

        $this->assertSame(PublicationStatus::Published, $due->refresh()->status);
        $this->assertNotNull($due->published_at);
        $this->assertSame(PublicationStatus::Scheduled, $future->refresh()->status);
    }

    public function test_revision_restore_preserves_a_new_lock_version_and_records_the_restore(): void
    {
        $page = Page::query()->create([
            'slug' => 'revision-test',
            'template_key' => 'revision-test',
            'title' => 'Original',
            'status' => PublicationStatus::Draft,
        ]);
        $originalRevision = $page->revisions()->oldest('version')->firstOrFail();
        $page->forceFill(['title' => 'Changed', 'lock_version' => 2])->save();

        $page->restoreRevision($originalRevision);

        $page->refresh();
        $this->assertSame('Original', $page->title);
        $this->assertSame(3, $page->lock_version);
        $this->assertSame('restored', $page->revisions()->first()->event);
    }

    public function test_retention_command_removes_only_expired_records(): void
    {
        $expired = FormSubmission::query()->create([
            'type' => 'contact',
            'payload' => ['message' => 'Old'],
            'retention_until' => now()->subDay(),
        ]);
        $active = FormSubmission::query()->create([
            'type' => 'contact',
            'payload' => ['message' => 'Current'],
            'retention_until' => now()->addDay(),
        ]);
        Activity::query()->create([
            'log_name' => 'cms',
            'description' => 'expired event',
            'event' => 'test',
            'created_at' => now()->subMonths(13),
            'updated_at' => now()->subMonths(13),
        ]);

        $this->artisan('cms:purge-expired')->assertSuccessful();

        $this->assertModelMissing($expired);
        $this->assertModelExists($active);
        $this->assertDatabaseMissing('activity_log', ['description' => 'expired event']);
    }
}
