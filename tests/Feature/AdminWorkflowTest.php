<?php

namespace Tests\Feature;

use App\Enums\PublicationStatus;
use App\Livewire\Admin\PageEditor;
use App\Livewire\Admin\ResourceManager;
use App\Livewire\Admin\SettingsEditor;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Person;
use App\Models\Season;
use App\Models\SiteSetting;
use App\Models\Standing;
use App\Models\Team;
use App\Services\PageWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesCmsUsers;
use Tests\TestCase;

class AdminWorkflowTest extends TestCase
{
    use CreatesCmsUsers, RefreshDatabase;

    public function test_editor_can_publish_structured_content_immediately(): void
    {
        $editor = $this->cmsUser(['manage roster'], 'Editor');
        $person = Person::query()->create([
            'type' => 'player',
            'first_name' => 'Jordan',
            'last_name' => 'Miles',
            'display_name' => 'Jordan Miles',
            'slug' => 'jordan-miles',
            'status' => PublicationStatus::Draft,
        ]);

        $this->actingAs($editor);
        Livewire::test(ResourceManager::class, ['resource' => 'people'])
            ->call('edit', $person->id)
            ->set('form.status', 'published')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(PublicationStatus::Published, $person->refresh()->status);
    }

    public function test_page_editor_rejects_a_stale_save(): void
    {
        $publisher = $this->cmsUser(['manage pages', 'publish content'], 'Publisher');
        $page = Page::query()->create([
            'slug' => 'stale',
            'template_key' => 'stale',
            'title' => 'Stale Test',
            'status' => PublicationStatus::Draft,
            'workflow_status' => PublicationStatus::Draft,
        ]);
        PageSection::query()->create([
            'page_id' => $page->id,
            'section_key' => 'hero',
            'label' => 'Hero',
            'position' => 1,
            'field_schema' => ['fields' => []],
            'payload' => [],
        ]);

        $component = Livewire::actingAs($publisher)
            ->test(PageEditor::class, ['page' => $page]);

        Page::query()->whereKey($page)->update(['draft_lock_version' => 2]);

        $component
            ->set('pageForm.title', 'Conflicting title')
            ->call('save')
            ->assertHasErrors('pageForm');

        $this->assertSame('Stale Test', $page->refresh()->title);
    }

    public function test_publisher_can_schedule_a_page(): void
    {
        $publisher = $this->cmsUser(['manage pages', 'publish content'], 'Publisher');
        $page = Page::query()->create([
            'slug' => 'scheduled-page',
            'template_key' => 'scheduled-page',
            'title' => 'Scheduled Page',
            'status' => PublicationStatus::Published,
            'workflow_status' => PublicationStatus::Published,
            'published_at' => now(),
        ]);
        $workflow = app(PageWorkflowService::class);
        $snapshot = $workflow->snapshot($page);
        $snapshot['page']['title'] = 'Scheduled replacement';
        $page = $workflow->stage($page, $snapshot, 0, $publisher);
        $page = $workflow->submit($page, $publisher);
        $publishAt = now()->addDay()->format('Y-m-d\TH:i');

        Livewire::actingAs($publisher)
            ->test(PageEditor::class, ['page' => $page])
            ->set('publishAt', $publishAt)
            ->call('publish')
            ->assertHasNoErrors();

        $this->assertSame(PublicationStatus::Published, $page->refresh()->status);
        $this->assertSame(PublicationStatus::Scheduled, $page->workflow_status);
        $this->assertSame('Scheduled Page', $page->title);
        $this->assertNotNull($page->publish_at);
    }

    public function test_settings_editor_rejects_a_stale_save(): void
    {
        $administrator = $this->cmsUser(['manage settings'], 'Settings Administrator');
        $setting = SiteSetting::query()->create([
            'group' => 'branding',
            'key' => 'branding.site_name',
            'label' => 'Site name',
            'type' => 'text',
            'value' => ['value' => 'DMV Warriors'],
        ]);
        $component = Livewire::actingAs($administrator)->test(SettingsEditor::class);

        $setting->forceFill([
            'value' => ['value' => 'Changed elsewhere'],
            'lock_version' => 2,
        ])->saveQuietly();

        $component
            ->set("values.{$setting->id}", 'Conflicting change')
            ->call('save')
            ->assertHasErrors("values.{$setting->id}");

        $this->assertSame('Changed elsewhere', $setting->refresh()->value['value']);
    }

    public function test_settings_editor_accepts_internal_links_and_ignores_migration_markers(): void
    {
        $administrator = $this->cmsUser(['manage settings'], 'Settings Administrator');
        $link = SiteSetting::query()->create([
            'group' => 'tickets',
            'key' => 'tickets.global_url',
            'label' => 'Global ticket URL',
            'type' => 'url',
            'value' => ['value' => '/schedule'],
        ]);
        $marker = SiteSetting::query()->create([
            'group' => 'migration',
            'key' => 'migration.complete',
            'label' => 'Migration complete',
            'type' => 'boolean',
            'value' => ['value' => true],
        ]);

        Livewire::actingAs($administrator)
            ->test(SettingsEditor::class)
            ->assertSet("values.{$link->id}", '/schedule')
            ->assertSet("values.{$marker->id}", null)
            ->set("values.{$link->id}", '/tickets')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('/tickets', $link->refresh()->value['value']);
        $this->assertTrue($marker->refresh()->value['value']);
    }

    public function test_generic_resource_revision_can_be_restored(): void
    {
        $administrator = $this->cmsUser(['manage schedule'], 'Schedule Administrator');
        $season = Season::query()->create([
            'name' => 'Original season',
            'slug' => 'original-season',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
            'status' => PublicationStatus::Draft,
        ]);
        $originalRevision = $season->revisions()->oldest('version')->firstOrFail();

        $component = Livewire::actingAs($administrator)
            ->test(ResourceManager::class, ['resource' => 'seasons'])
            ->call('edit', $season->id)
            ->set('form.name', 'Updated season')
            ->call('save')
            ->call('edit', $season->id)
            ->call('restoreRevision', $originalRevision->id)
            ->assertHasNoErrors();

        $this->assertSame('Original season', $season->refresh()->name);
        $this->assertSame('draft_restored', $season->revisions()->first()->event);
        $this->assertSame($season->draft_lock_version, $component->get('originalLockVersion'));
    }

    public function test_populated_season_cannot_be_deleted(): void
    {
        $administrator = $this->cmsUser(['manage schedule'], 'Schedule Administrator');
        $season = Season::query()->create([
            'name' => 'Protected season',
            'slug' => 'protected-season',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
            'status' => PublicationStatus::Draft,
        ]);
        $team = Team::query()->create([
            'name' => 'DMV Warriors',
            'slug' => 'dmv-warriors',
            'status' => PublicationStatus::Draft,
        ]);
        Standing::query()->create([
            'season_id' => $season->id,
            'team_id' => $team->id,
            'rank' => 1,
            'wins' => 0,
            'losses' => 0,
            'win_percentage' => 0,
        ]);

        Livewire::actingAs($administrator)
            ->test(ResourceManager::class, ['resource' => 'seasons'])
            ->call('delete', $season->id)
            ->assertStatus(422);

        $this->assertModelExists($season);
        $this->assertModelExists(Standing::query()->firstOrFail());
    }
}
