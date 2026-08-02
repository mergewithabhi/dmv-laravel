<?php

namespace Tests\Feature;

use App\Enums\PublicationStatus;
use App\Livewire\Admin\ContentPermissionsEditor;
use App\Livewire\Admin\PageEditor;
use App\Livewire\Admin\PagesIndex;
use App\Models\ContentPermission;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesCmsUsers;
use Tests\TestCase;

class ContentPermissionGrantTest extends TestCase
{
    use CreatesCmsUsers, RefreshDatabase;

    public function test_user_without_manage_pages_but_with_a_field_group_grant_can_edit_only_that_field(): void
    {
        [$page, $heroSection, $introSection] = $this->pageWithTwoSections();

        $grantee = $this->cmsUser([], 'Contributor');
        ContentPermission::query()->create([
            'user_id' => $grantee->id,
            'template_key' => $page->template_key,
            'section_key' => $heroSection->section_key,
            'field_group' => 'heading',
        ]);

        $component = Livewire::actingAs($grantee)
            ->test(PageEditor::class, ['page' => $page])
            ->set("sections.{$heroSection->id}.payload.headline", 'Edited by contributor')
            ->set("sections.{$introSection->id}.payload.body", 'Should be blocked')
            ->call('save')
            ->assertHasNoErrors();

        $page->refresh();
        $this->assertSame('Edited by contributor', $page->draft_snapshot['sections'][$heroSection->id]['payload']['headline']);
        $this->assertSame('Intro copy', $page->draft_snapshot['sections'][$introSection->id]['payload']['body']);
    }

    public function test_user_without_any_grant_or_manage_pages_cannot_open_the_page_editor(): void
    {
        [$page] = $this->pageWithTwoSections();
        $stranger = $this->cmsUser([], 'Nobody');

        Livewire::actingAs($stranger)
            ->test(PageEditor::class, ['page' => $page])
            ->assertForbidden();
    }

    public function test_pages_index_only_lists_pages_the_granular_user_was_granted(): void
    {
        [$page] = $this->pageWithTwoSections();
        $otherPage = Page::query()->create([
            'slug' => 'sponsors',
            'template_key' => 'sponsors',
            'title' => 'Sponsors',
            'status' => PublicationStatus::Published,
            'workflow_status' => PublicationStatus::Published,
            'published_at' => now(),
        ]);

        $grantee = $this->cmsUser([], 'Contributor');
        ContentPermission::query()->create([
            'user_id' => $grantee->id,
            'template_key' => $page->template_key,
            'section_key' => '*',
            'field_group' => '*',
        ]);

        $rendered = Livewire::actingAs($grantee)
            ->test(PagesIndex::class)
            ->assertOk();

        $rendered->assertSee($page->title)->assertDontSee($otherPage->title);
    }

    public function test_super_admin_is_never_restricted_by_missing_grants(): void
    {
        [$page, $heroSection] = $this->pageWithTwoSections();
        $superAdmin = $this->cmsUser(
            ['manage pages', 'publish content', 'manage users'],
            'Super Admin'
        );

        Livewire::actingAs($superAdmin)
            ->test(PageEditor::class, ['page' => $page])
            ->set('pageForm.title', 'Retitled by super admin')
            ->set("sections.{$heroSection->id}.payload.headline", 'Super admin edit')
            ->call('save')
            ->assertHasNoErrors();

        $page->refresh();
        $this->assertSame('Retitled by super admin', $page->draft_snapshot['page']['title']);
        $this->assertSame('Super admin edit', $page->draft_snapshot['sections'][$heroSection->id]['payload']['headline']);
    }

    public function test_only_super_admin_can_manage_content_permission_grants(): void
    {
        $publisher = $this->cmsUser(['manage pages', 'publish content', 'manage users'], 'Publisher');
        $target = User::factory()->create();

        Livewire::actingAs($publisher)
            ->test(ContentPermissionsEditor::class, ['user' => $target])
            ->assertForbidden();
    }

    private function pageWithTwoSections(): array
    {
        $page = Page::query()->create([
            'slug' => 'about',
            'template_key' => 'about',
            'title' => 'About',
            'status' => PublicationStatus::Published,
            'workflow_status' => PublicationStatus::Published,
            'published_at' => now(),
        ]);

        $hero = PageSection::query()->create([
            'page_id' => $page->id,
            'section_key' => 'hero',
            'label' => 'Hero',
            'position' => 1,
            'field_schema' => [
                'fields' => [
                    'headline' => ['label' => 'h1 - Headline', 'kind' => 'text', 'input' => 'text', 'max' => 180],
                ],
            ],
            'payload' => ['headline' => 'Original headline'],
            'template_html' => '<section><h1>Original headline</h1></section>',
        ]);

        $intro = PageSection::query()->create([
            'page_id' => $page->id,
            'section_key' => 'introduction',
            'label' => 'Introduction',
            'position' => 2,
            'field_schema' => [
                'fields' => [
                    'body' => ['label' => 'p - Body', 'kind' => 'text', 'input' => 'textarea', 'max' => 5000],
                ],
            ],
            'payload' => ['body' => 'Intro copy'],
            'template_html' => '<section><p>Intro copy</p></section>',
        ]);

        return [$page->refresh()->load('sections'), $hero->refresh(), $intro->refresh()];
    }
}
