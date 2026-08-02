<?php

namespace Tests\Feature;

use App\Enums\PublicationStatus;
use App\Livewire\Admin\AuditLog;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\MediaLibrary;
use App\Livewire\Admin\PageEditor;
use App\Livewire\Admin\PagesIndex;
use App\Livewire\Admin\ResourceManager;
use App\Livewire\Admin\SecurityProfile;
use App\Livewire\Admin\SettingsEditor;
use App\Livewire\Admin\SubmissionsInbox;
use App\Livewire\Admin\UsersManager;
use App\Models\FormSubmission;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Person;
use App\Models\User;
use App\Services\PageWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesCmsUsers;
use Tests\TestCase;

class AdminInteractionAuditTest extends TestCase
{
    use CreatesCmsUsers, RefreshDatabase;

    public function test_confirmations_use_the_shared_dialog_instead_of_browser_prompts(): void
    {
        $views = collect(glob(resource_path('views/livewire/admin/*.blade.php')))
            ->map(fn (string $path): string => file_get_contents($path))
            ->implode("\n");

        $this->assertStringNotContainsString('wire:confirm', $views);
        $this->assertGreaterThanOrEqual(10, substr_count($views, 'data-confirm-message='));

        foreach (['admin', 'auth', 'site'] as $layout) {
            $markup = file_get_contents(
                resource_path("views/components/layouts/{$layout}.blade.php")
            );
            $this->assertStringContainsString(
                "@include('components.confirm-dialog')",
                $markup
            );
        }

        $script = file_get_contents(resource_path('js/confirm-dialog.js'));
        $this->assertStringContainsString('dialog.showModal()', $script);
        $this->assertStringContainsString('event.stopImmediatePropagation()', $script);
        $this->assertStringNotContainsString('window.confirm(', $script);
        $this->assertStringNotContainsString('window.prompt(', $script);
    }

    public function test_admin_action_triggers_have_focusable_destination_panels(): void
    {
        $views = collect([
            'media-library.blade.php',
            'resource-manager.blade.php',
            'security-profile.blade.php',
            'submissions-inbox.blade.php',
            'users-manager.blade.php',
        ])->map(
            fn (string $view): string => file_get_contents(
                resource_path('views/livewire/admin/'.$view)
            )
        )->implode("\n");

        preg_match_all('/data-admin-focus-target="#([^"]+)"/', $views, $matches);
        $targets = array_unique($matches[1]);

        $this->assertNotEmpty($targets);
        foreach ($targets as $target) {
            $this->assertMatchesRegularExpression(
                '/id="'.preg_quote($target, '/').'".{0,300}data-admin-action-area/s',
                $views,
                "Admin focus target #{$target} must identify an action area."
            );
        }

        $script = file_get_contents(resource_path('js/admin-actions.js'));
        $this->assertStringContainsString('Livewire.hook("commit"', $script);
        $this->assertStringContainsString('prefers-reduced-motion: reduce', $script);
        $this->assertStringContainsString('target.focus({ preventScroll: true })', $script);
        $this->assertStringContainsString('target.scrollIntoView({', $script);
    }

    public function test_admin_view_actions_resolve_to_public_component_methods(): void
    {
        $views = [
            'audit-log.blade.php' => AuditLog::class,
            'media-library.blade.php' => MediaLibrary::class,
            'page-editor.blade.php' => PageEditor::class,
            'pages-index.blade.php' => PagesIndex::class,
            'resource-manager.blade.php' => ResourceManager::class,
            'security-profile.blade.php' => SecurityProfile::class,
            'settings-editor.blade.php' => SettingsEditor::class,
            'submissions-inbox.blade.php' => SubmissionsInbox::class,
            'users-manager.blade.php' => UsersManager::class,
        ];

        foreach ($views as $view => $component) {
            $markup = file_get_contents(resource_path('views/livewire/admin/'.$view));
            preg_match_all('/wire:(?:click|submit)="([A-Za-z][A-Za-z0-9_]*)/', $markup, $matches);

            foreach (array_unique($matches[1]) as $action) {
                $this->assertTrue(
                    method_exists($component, $action),
                    "{$view} references missing {$component}::{$action}()."
                );
                $this->assertTrue(
                    (new \ReflectionMethod($component, $action))->isPublic(),
                    "{$component}::{$action}() must be public."
                );
            }
        }
    }

    public function test_dashboard_hides_modules_the_current_admin_cannot_access(): void
    {
        $editor = $this->cmsUser(['manage pages'], 'Dashboard Editor');
        FormSubmission::query()->create([
            'type' => 'contact',
            'status' => 'new',
            'name' => 'Private Visitor',
            'payload' => ['message' => 'Private'],
        ]);

        Livewire::actingAs($editor)
            ->test(Dashboard::class)
            ->assertSee('Review pages')
            ->assertDontSee('Recent submissions')
            ->assertDontSee('Private Visitor')
            ->assertDontSee('Upcoming games');
    }

    public function test_resource_filters_reset_pagination_and_hidden_selection(): void
    {
        $editor = $this->cmsUser(['manage roster'], 'Roster Editor');

        Livewire::actingAs($editor)
            ->test(ResourceManager::class, ['resource' => 'people'])
            ->set('selected', [999])
            ->set('paginators.page', 4)
            ->set('search', 'Jordan')
            ->assertSet('selected', [])
            ->assertSet('paginators.page', 1)
            ->set('statusFilter', 'draft')
            ->assertSet('paginators.page', 1);
    }

    public function test_bulk_publish_accepts_only_draft_records(): void
    {
        $publisher = $this->cmsUser(['manage roster', 'publish content'], 'Roster Publisher');
        $draft = $this->person('Draft Player', PublicationStatus::Draft);
        $published = $this->person('Published Player', PublicationStatus::Published);

        Livewire::actingAs($publisher)
            ->test(ResourceManager::class, ['resource' => 'people'])
            ->set('selected', [$published->id])
            ->call('bulkPublish')
            ->assertHasErrors('selected');

        $this->assertSame(PublicationStatus::Published, $published->refresh()->status);

        Livewire::actingAs($publisher)
            ->test(ResourceManager::class, ['resource' => 'people'])
            ->set('selected', [$draft->id])
            ->call('bulkPublish')
            ->assertHasNoErrors();

        $this->assertSame(PublicationStatus::Published, $draft->refresh()->status);
    }

    public function test_review_publish_rejects_unsaved_page_changes(): void
    {
        $publisher = $this->cmsUser(['manage pages', 'publish content'], 'Page Publisher');
        $page = Page::query()->create([
            'slug' => 'audit-page',
            'template_key' => 'audit-page',
            'title' => 'Published title',
            'status' => PublicationStatus::Published,
            'workflow_status' => PublicationStatus::Published,
            'published_at' => now(),
        ]);
        PageSection::query()->create([
            'page_id' => $page->id,
            'section_key' => 'hero',
            'label' => 'Hero',
            'position' => 1,
            'field_schema' => ['fields' => []],
            'payload' => [],
        ]);
        $workflow = app(PageWorkflowService::class);
        $page = $workflow->stage($page->refresh(), $workflow->snapshot($page->refresh()), 0, $publisher);
        $page = $workflow->submit($page, $publisher);

        Livewire::actingAs($publisher)
            ->test(PageEditor::class, ['page' => $page])
            ->set('pageForm.title', 'Unsaved review edit')
            ->call('publish')
            ->assertHasErrors('pageForm');

        $this->assertSame('Published title', $page->refresh()->title);
        $this->assertSame(PublicationStatus::InReview, $page->workflow_status);
    }

    public function test_submission_cannot_be_assigned_to_a_user_without_inbox_access(): void
    {
        $manager = $this->cmsUser(['manage submissions'], 'Inbox Auditor');
        $outsider = $this->cmsUser(['manage pages'], 'Page Only User');
        $submission = FormSubmission::query()->create([
            'type' => 'contact',
            'status' => 'new',
            'payload' => ['message' => 'Hello'],
        ]);

        Livewire::actingAs($manager)
            ->test(SubmissionsInbox::class)
            ->call('select', $submission->id)
            ->set('assignedTo', $outsider->id)
            ->call('save')
            ->assertHasErrors('assignedTo');

        $this->assertNull($submission->refresh()->assigned_to);
    }

    public function test_required_two_factor_authentication_cannot_be_disabled(): void
    {
        config()->set('cms.security.require_two_factor', true);
        $administrator = $this->cmsUser([], 'Security Administrator');
        $administrator->forceFill([
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt('[]'),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->actingAs($administrator)->withSession([
            'auth.password_confirmed_at' => time(),
        ]);

        Livewire::test(SecurityProfile::class)
            ->assertDontSee('Disable 2FA')
            ->call('disable')
            ->assertStatus(422);

        $this->assertNotNull($administrator->refresh()->two_factor_confirmed_at);
    }

    public function test_media_referenced_only_by_a_page_draft_cannot_be_deleted(): void
    {
        $manager = $this->cmsUser(['manage media'], 'Media Auditor');
        $asset = MediaAsset::query()->create([
            'kind' => 'image',
            'title' => 'Draft hero',
            'alt_text' => 'Draft hero',
            'created_by' => $manager->id,
        ]);
        $page = Page::query()->create([
            'slug' => 'draft-media',
            'template_key' => 'draft-media',
            'title' => 'Draft media',
            'status' => PublicationStatus::Draft,
            'workflow_status' => PublicationStatus::Draft,
            'draft_snapshot' => [
                'page' => ['og_media_id' => $asset->id],
                'sections' => [],
            ],
        ]);

        Livewire::actingAs($manager)
            ->test(MediaLibrary::class)
            ->call('delete', $asset->id)
            ->assertStatus(422);

        $this->assertModelExists($asset);
        $this->assertModelExists($page);
    }

    public function test_media_referenced_only_by_a_relational_draft_cannot_be_deleted(): void
    {
        $manager = $this->cmsUser(['manage media'], 'Draft Media Manager');
        $asset = MediaAsset::query()->create([
            'kind' => 'image',
            'title' => 'Draft player photo',
            'alt_text' => 'Draft player',
            'created_by' => $manager->id,
        ]);
        $person = Person::query()->create([
            'type' => 'player',
            'first_name' => 'Draft',
            'last_name' => 'Photo',
            'display_name' => 'Draft Photo',
            'slug' => 'draft-photo',
            'status' => PublicationStatus::Published,
            'published_at' => now(),
            'draft_snapshot' => [
                'display_name' => 'Draft Photo',
                'photo_media_id' => $asset->id,
            ],
            'workflow_status' => PublicationStatus::Draft,
        ]);

        Livewire::actingAs($manager)
            ->test(MediaLibrary::class)
            ->call('delete', $asset->id)
            ->assertStatus(422);

        $this->assertModelExists($asset);
        $this->assertModelExists($person);
    }

    public function test_user_manager_cannot_grant_permissions_the_actor_does_not_have(): void
    {
        $manager = $this->cmsUser(['manage users'], 'Limited User Manager');
        Permission::findOrCreate('manage pages', 'web');
        $privilegedRole = Role::findOrCreate('Privileged Editor', 'web');
        $privilegedRole->syncPermissions(['access admin', 'manage pages']);

        $this->actingAs($manager)->withSession([
            'auth.password_confirmed_at' => time(),
        ]);

        Livewire::test(UsersManager::class)
            ->call('create')
            ->set('form.name', 'Escalated User')
            ->set('form.email', 'escalated@example.test')
            ->set('form.password', 'Long-Enough-Password-2026!')
            ->set('form.role', $privilegedRole->name)
            ->call('save')
            ->assertHasErrors('form.role');

        $this->assertFalse(User::query()->where('email', 'escalated@example.test')->exists());
    }

    private function person(string $name, PublicationStatus $status): Person
    {
        return Person::query()->create([
            'type' => 'player',
            'first_name' => str($name)->before(' '),
            'last_name' => str($name)->after(' '),
            'display_name' => $name,
            'slug' => str($name)->slug(),
            'status' => $status,
        ]);
    }
}
