<?php

namespace Tests\Feature;

use App\Domain\Content\PageSchemaRegistry;
use App\Domain\Content\SectionContentExtractor;
use App\Enums\MediaKind;
use App\Enums\PublicationStatus;
use App\Livewire\Admin\PageEditor;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\PageSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\CreatesCmsUsers;
use Tests\TestCase;

class PageDraftWorkflowTest extends TestCase
{
    use CreatesCmsUsers, RefreshDatabase;

    public function test_a_newly_selected_media_field_is_published_when_changes_are_saved(): void
    {
        Storage::fake('public');
        [$page, $section, $fieldId] = $this->publishedPageWithHeroMediaField();
        $asset = MediaAsset::query()->create([
            'kind' => MediaKind::Image->value,
            'title' => 'Hero photo',
            'alt_text' => 'Hero photo',
        ]);
        $asset->addMediaFromString('fake-image-bytes')->usingFileName('hero.jpg')->toMediaCollection('file');
        $publisher = $this->cmsUser(['manage pages', 'publish content'], 'Publisher');

        $component = Livewire::actingAs($publisher)
            ->test(PageEditor::class, ['page' => $page])
            ->set("sections.{$section->id}.payload.{$fieldId}", (string) $asset->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->get('/about')->assertOk()->assertSee($asset->url('web'), false);
    }

    private function publishedPageWithHeroMediaField(): array
    {
        $registry = app(PageSchemaRegistry::class);
        $html = file_get_contents($registry->templatePath('about'));
        $extracted = app(SectionContentExtractor::class)->extract($html, '.about-hero-media');
        $fieldId = collect($extracted['schema']['fields'])
            ->first(fn (array $field) => ($field['input'] ?? null) === 'media')['id'];

        $page = Page::query()->create([
            'slug' => 'about',
            'template_key' => 'about',
            'title' => 'About',
            'status' => PublicationStatus::Published,
            'workflow_status' => PublicationStatus::Published,
            'published_at' => now(),
        ]);
        $section = PageSection::query()->create([
            'page_id' => $page->id,
            'section_key' => 'hero',
            'label' => 'Hero',
            'position' => 1,
            'field_schema' => $extracted['schema'],
            'payload' => array_replace($extracted['payload'], [$fieldId => '']),
        ]);

        return [$page->refresh()->load('sections'), $section->refresh(), $fieldId];
    }

    public function test_authorized_editor_changes_are_published_immediately(): void
    {
        [$page, $section] = $this->publishedPage();
        $editor = $this->cmsUser(['manage pages'], 'Editor');

        $editorComponent = Livewire::actingAs($editor)
            ->test(PageEditor::class, ['page' => $page])
            ->set('pageForm.title', 'Draft About')
            ->set("sections.{$section->id}.payload.headline", 'Draft headline')
            ->call('save')
            ->assertHasNoErrors();

        $page->refresh();
        $section->refresh();
        $this->assertSame('Draft About', $page->title);
        $this->assertSame('Draft headline', $section->payload['headline']);
        $this->assertSame(PublicationStatus::Published, $page->status);
        $this->assertSame(PublicationStatus::Published, $page->workflow_status);
        $this->assertNull($page->draft_snapshot);
        $this->assertTrue(Page::query()->published()->whereKey($page)->exists());
        $this->assertNotNull($page->published_at);
    }

    public function test_stale_editor_cannot_overwrite_a_newer_draft(): void
    {
        [$page] = $this->publishedPage();
        $editor = $this->cmsUser(['manage pages'], 'Editor');
        $firstEditor = Livewire::actingAs($editor)
            ->test(PageEditor::class, ['page' => $page]);
        $staleEditor = Livewire::actingAs($editor)
            ->test(PageEditor::class, ['page' => $page]);

        $firstEditor
            ->set('pageForm.title', 'First draft')
            ->call('save')
            ->assertHasNoErrors();

        $staleEditor
            ->set('pageForm.title', 'Stale draft')
            ->call('save')
            ->assertHasErrors('pageForm');

        $page->refresh();
        $this->assertSame('First draft', $page->title);
        $this->assertNull($page->draft_snapshot);
        $this->assertSame(1, $page->draft_lock_version);
    }

    public function test_scheduled_approval_keeps_live_content_until_due(): void
    {
        [$page, $section] = $this->publishedPage();
        $publisher = $this->cmsUser(['manage pages', 'publish content'], 'Publisher');
        $publishAt = now()->addHour()->startOfMinute();

        $workflow = app(\App\Services\PageWorkflowService::class);
        $snapshot = $workflow->snapshot($page);
        $snapshot['page']['title'] = 'Scheduled About';
        $snapshot['sections'][$section->id]['payload']['headline'] = 'Scheduled headline';
        $page = $workflow->stage($page, $snapshot, 0, $publisher);
        $page = $workflow->submit($page, $publisher);
        $workflow->approve($page, $publisher, $publishAt);

        $page->refresh();
        $this->assertSame('Live About', $page->title);
        $this->assertSame(PublicationStatus::Published, $page->status);
        $this->assertSame(PublicationStatus::Scheduled, $page->workflow_status);

        $this->travelTo($publishAt->copy()->addSecond());
        $this->artisan('cms:publish-scheduled')
            ->expectsOutput('Published 1 scheduled record(s).')
            ->assertSuccessful();

        $page->refresh();
        $section->refresh();
        $this->assertSame('Scheduled About', $page->title);
        $this->assertSame('Scheduled headline', $section->payload['headline']);
        $this->assertSame(PublicationStatus::Published, $page->workflow_status);
    }

    private function publishedPage(): array
    {
        $page = Page::query()->create([
            'slug' => 'about',
            'template_key' => 'about',
            'title' => 'Live About',
            'status' => PublicationStatus::Published,
            'workflow_status' => PublicationStatus::Published,
            'published_at' => now(),
        ]);
        $section = PageSection::query()->create([
            'page_id' => $page->id,
            'section_key' => 'hero',
            'label' => 'Hero',
            'position' => 1,
            'field_schema' => [
                'fields' => [
                    'headline' => [
                        'label' => 'Headline',
                        'input' => 'text',
                        'max' => 180,
                    ],
                ],
            ],
            'payload' => ['headline' => 'Live headline'],
            'template_html' => '<section><h1>Live headline</h1></section>',
        ]);

        return [$page->refresh(), $section->refresh()];
    }
}
