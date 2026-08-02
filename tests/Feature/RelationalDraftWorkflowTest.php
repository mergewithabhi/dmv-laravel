<?php

namespace Tests\Feature;

use App\Enums\PublicationStatus;
use App\Livewire\Admin\ResourceManager;
use App\Models\Person;
use App\Models\Season;
use App\Models\Team;
use App\Services\ResourceWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\CreatesCmsUsers;
use Tests\TestCase;

class RelationalDraftWorkflowTest extends TestCase
{
    use CreatesCmsUsers, RefreshDatabase;

    public function test_saved_draft_can_be_published_immediately_without_review(): void
    {
        $person = $this->person('Live Player', PublicationStatus::Published);
        $editor = $this->cmsUser(['manage roster'], 'Roster Editor');

        Livewire::actingAs($editor)
            ->test(ResourceManager::class, ['resource' => 'people'])
            ->call('edit', $person->id)
            ->set('form.display_name', 'Reviewed Player')
            ->set('form.status', PublicationStatus::Draft->value)
            ->call('save')
            ->assertHasNoErrors();

        $person->refresh();
        $this->assertSame('Live Player', $person->display_name);
        $this->assertSame(PublicationStatus::Published, $person->status);
        $this->assertSame(PublicationStatus::Draft, $person->workflow_status);
        $this->assertSame('Reviewed Player', $person->draft_snapshot['display_name']);

        $publisher = $this->cmsUser(
            ['manage roster', 'publish content'],
            'Roster Publisher'
        );
        Livewire::actingAs($publisher)
            ->test(ResourceManager::class, ['resource' => 'people'])
            ->call('edit', $person->id)
            ->set('form.status', PublicationStatus::Published->value)
            ->call('save')
            ->assertHasNoErrors();

        $person->refresh();
        $this->assertSame('Reviewed Player', $person->display_name);
        $this->assertSame(PublicationStatus::Published, $person->workflow_status);
        $this->assertNull($person->draft_snapshot);
    }

    public function test_stale_relational_draft_is_rejected(): void
    {
        $person = $this->person('Concurrent Player', PublicationStatus::Published);
        $editor = $this->cmsUser(['manage roster'], 'Concurrent Editor');
        $first = Livewire::actingAs($editor)
            ->test(ResourceManager::class, ['resource' => 'people'])
            ->call('edit', $person->id);
        $stale = Livewire::actingAs($editor)
            ->test(ResourceManager::class, ['resource' => 'people'])
            ->call('edit', $person->id);

        $first
            ->set('form.display_name', 'First Draft')
            ->set('form.status', PublicationStatus::Draft->value)
            ->call('save')
            ->assertHasNoErrors();

        $stale
            ->set('form.display_name', 'Stale Draft')
            ->set('form.status', PublicationStatus::Draft->value)
            ->call('save')
            ->assertHasErrors('form');

        $this->assertSame('First Draft', $person->refresh()->draft_snapshot['display_name']);
    }

    public function test_scheduled_relational_edit_keeps_live_content_until_due(): void
    {
        $person = $this->person('Live Scheduled Player', PublicationStatus::Published);
        $publisher = $this->cmsUser(
            ['manage roster', 'publish content'],
            'Scheduled Publisher'
        );
        $publishAt = now()->addHour()->startOfMinute();

        Livewire::actingAs($publisher)
            ->test(ResourceManager::class, ['resource' => 'people'])
            ->call('edit', $person->id)
            ->set('form.display_name', 'Future Player')
            ->set('form.status', PublicationStatus::Scheduled->value)
            ->set('form.publish_at', $publishAt->format('Y-m-d\TH:i'))
            ->call('save')
            ->assertHasNoErrors();

        $person->refresh();
        $this->assertSame('Live Scheduled Player', $person->display_name);
        $this->assertSame(PublicationStatus::Published, $person->status);
        $this->assertSame(PublicationStatus::Scheduled, $person->workflow_status);

        $this->travelTo($publishAt->copy()->addSecond());
        $this->artisan('cms:publish-scheduled')->assertSuccessful();

        $person->refresh();
        $this->assertSame('Future Player', $person->display_name);
        $this->assertSame(PublicationStatus::Published, $person->workflow_status);
        $this->assertNull($person->draft_snapshot);
    }

    public function test_publish_at_is_only_shown_for_scheduled_records(): void
    {
        $person = $this->person('Status Player', PublicationStatus::Published);
        $publisher = $this->cmsUser(
            ['manage roster', 'publish content'],
            'Status Publisher'
        );

        Livewire::actingAs($publisher)
            ->test(ResourceManager::class, ['resource' => 'people'])
            ->call('edit', $person->id)
            ->assertDontSee('Publish at')
            ->assertDontSee('In review')
            ->set('form.status', PublicationStatus::Scheduled->value)
            ->assertSee('Publish at')
            ->set('form.status', PublicationStatus::Draft->value)
            ->assertDontSee('Publish at');
    }

    public function test_deleted_resource_moves_to_trash_and_can_be_restored(): void
    {
        $person = $this->person('Recoverable Player', PublicationStatus::Draft);
        $editor = $this->cmsUser(['manage roster'], 'Trash Editor');

        Livewire::actingAs($editor)
            ->test(ResourceManager::class, ['resource' => 'people'])
            ->call('delete', $person->id)
            ->assertHasNoErrors()
            ->set('statusFilter', 'trashed')
            ->assertSee('Recoverable Player')
            ->assertSee('Restore')
            ->call('restore', $person->id)
            ->assertHasNoErrors();

        $this->assertNotSoftDeleted('people', ['id' => $person->id]);
        $this->assertNotNull(Person::query()->find($person->id));
    }

    public function test_game_validation_requires_scores_and_exactly_one_dmv_team(): void
    {
        $editor = $this->cmsUser(['manage schedule'], 'Schedule Editor');
        $season = Season::query()->create([
            'name' => '2026',
            'slug' => '2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
            'status' => PublicationStatus::Draft,
        ]);
        $dmv = $this->team('DMV Warriors', true);
        $opponent = $this->team('Capital Kings');
        $otherOpponent = $this->team('Maryland Stars');

        $component = Livewire::actingAs($editor)
            ->test(ResourceManager::class, ['resource' => 'games'])
            ->set('form.season_id', $season->id)
            ->set('form.home_team_id', $dmv->id)
            ->set('form.away_team_id', $opponent->id)
            ->set('form.slug', 'dmv-vs-capital')
            ->set('form.starts_at', '2026-09-01T19:00')
            ->set('form.status', 'final')
            ->set('form.publication_status', PublicationStatus::Draft->value)
            ->call('save')
            ->assertHasErrors(['form.home_score', 'form.away_score']);

        $component
            ->set('form.status', 'scheduled')
            ->set('form.home_team_id', $opponent->id)
            ->set('form.away_team_id', $otherOpponent->id)
            ->call('save')
            ->assertHasErrors(['form.home_team_id', 'form.away_team_id']);

        $this->assertDatabaseCount('games', 0);
    }

    public function test_duplicate_slug_returns_validation_error(): void
    {
        $this->person('Existing Player', PublicationStatus::Draft, 'duplicate-player');
        $editor = $this->cmsUser(['manage roster'], 'Slug Editor');

        Livewire::actingAs($editor)
            ->test(ResourceManager::class, ['resource' => 'people'])
            ->set('form.type', 'player')
            ->set('form.first_name', 'Second')
            ->set('form.last_name', 'Player')
            ->set('form.display_name', 'Second Player')
            ->set('form.slug', 'duplicate-player')
            ->set('form.status', PublicationStatus::Draft->value)
            ->call('save')
            ->assertHasErrors('form.slug');
    }

    public function test_publishing_a_new_current_season_atomically_replaces_the_old_one(): void
    {
        $publisher = $this->cmsUser(['publish content'], 'Workflow Publisher');
        $old = Season::query()->create([
            'name' => '2025',
            'slug' => '2025',
            'starts_on' => '2025-01-01',
            'ends_on' => '2025-12-31',
            'is_current' => true,
            'status' => PublicationStatus::Published,
            'published_at' => now(),
        ]);
        $workflow = app(ResourceWorkflowService::class);
        $new = $workflow->create(new Season, [
            'name' => '2026',
            'slug' => '2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
            'is_current' => true,
        ], 'status', $publisher);
        $new = $workflow->publish($new, 'status', $publisher);

        $this->assertFalse($old->refresh()->is_current);
        $this->assertTrue($new->is_current);
        $this->assertSame(1, Season::query()->where('is_current', true)->count());

        $new = $workflow->stage(
            $new,
            array_replace($new->only([
                'name',
                'slug',
                'starts_on',
                'ends_on',
                'is_current',
            ]), ['is_current' => false]),
            $new->draft_lock_version,
            $publisher
        );
        $this->expectException(ValidationException::class);
        $workflow->publish($new, 'status', $publisher);
    }

    private function person(
        string $name,
        PublicationStatus $status,
        ?string $slug = null
    ): Person {
        return Person::query()->create([
            'type' => 'player',
            'first_name' => str($name)->before(' '),
            'last_name' => str($name)->after(' '),
            'display_name' => $name,
            'slug' => $slug ?: str($name)->slug(),
            'status' => $status,
            'published_at' => $status === PublicationStatus::Published ? now() : null,
        ]);
    }

    private function team(string $name, bool $isDmv = false): Team
    {
        return Team::query()->create([
            'name' => $name,
            'slug' => str($name)->slug(),
            'is_home_team' => $isDmv,
            'status' => PublicationStatus::Draft,
        ]);
    }
}
