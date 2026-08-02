<?php

namespace Tests\Feature;

use App\Domain\Admin\ResourceRegistry;
use App\Enums\PublicationStatus;
use App\Livewire\Admin\AuditLog;
use App\Livewire\Admin\MediaLibrary;
use App\Livewire\Admin\PageEditor;
use App\Livewire\Admin\PagesIndex;
use App\Livewire\Admin\ResourceManager;
use App\Livewire\Admin\SettingsEditor;
use App\Livewire\Admin\SubmissionsInbox;
use App\Livewire\Admin\UsersManager;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\CreatesCmsUsers;
use Tests\TestCase;

class AdminSecurityRegressionTest extends TestCase
{
    use CreatesCmsUsers, RefreshDatabase;

    public function test_inbox_manager_cannot_mount_unrelated_sensitive_components(): void
    {
        $inboxManager = $this->cmsUser(['manage submissions', 'export submissions'], 'Inbox Manager');
        $page = Page::query()->create([
            'slug' => 'about',
            'template_key' => 'about',
            'title' => 'About',
            'status' => PublicationStatus::Published,
            'workflow_status' => PublicationStatus::Published,
        ]);

        Livewire::actingAs($inboxManager);

        Livewire::test(PagesIndex::class)->assertForbidden();
        Livewire::test(PageEditor::class, ['page' => $page])->assertForbidden();
        Livewire::test(MediaLibrary::class)->assertForbidden();
        Livewire::test(SettingsEditor::class)->assertForbidden();
        Livewire::test(AuditLog::class)->assertForbidden();
        Livewire::test(UsersManager::class)->assertForbidden();
    }

    public function test_editor_cannot_mount_or_mutate_the_submissions_inbox(): void
    {
        $editor = $this->cmsUser(['manage pages'], 'Editor');

        Livewire::actingAs($editor);
        Livewire::test(SubmissionsInbox::class)->assertForbidden();
    }

    public function test_admin_routes_enforce_resource_specific_permissions(): void
    {
        $inboxManager = $this->enableTwoFactor(
            $this->cmsUser(['manage submissions'], 'Inbox Manager')
        );

        $this->actingAs($inboxManager);

        $this->get('/admin/submissions')->assertOk();
        $this->get('/admin/pages')->assertForbidden();
        $this->get('/admin/media')->assertForbidden();
        $this->get('/admin/settings')->assertForbidden();
        $this->get('/admin/audit')->assertForbidden();
        $this->get('/admin/users')->assertForbidden();
    }

    public function test_client_cannot_change_the_locked_resource_key(): void
    {
        $editor = $this->cmsUser(['manage roster'], 'Editor');
        Person::query()->create([
            'type' => 'player',
            'first_name' => 'Jordan',
            'last_name' => 'Miles',
            'display_name' => 'Jordan Miles',
            'slug' => 'jordan-miles',
            'status' => PublicationStatus::Draft,
        ]);
        $component = Livewire::actingAs($editor)
            ->test(ResourceManager::class, ['resource' => 'people']);

        try {
            $component->set('resource', 'navigation');
            $this->fail('Livewire accepted a client mutation of the locked resource property.');
        } catch (CannotUpdateLockedPropertyException $exception) {
            $this->assertSame('resource', $exception->property);
        }

        $this->assertSame('people', $component->get('resource'));
    }

    public function test_resource_actions_reauthorize_after_server_side_resource_tampering(): void
    {
        $editor = $this->cmsUser(['manage roster'], 'Editor');
        $navigation = NavigationItem::query()->create([
            'location' => 'primary',
            'label' => 'Protected',
            'url' => '/protected',
            'target' => '_self',
            'position' => 1,
            'is_enabled' => true,
        ]);
        $component = Livewire::actingAs($editor)
            ->test(ResourceManager::class, ['resource' => 'people']);
        $component->instance()->resource = 'navigation';

        try {
            $component->instance()->delete($navigation->id, app(ResourceRegistry::class));
            $this->fail('A resource action ran without reauthorizing the mutated resource.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertModelExists($navigation);
    }

    public function test_users_manager_requires_recent_password_confirmation(): void
    {
        $administrator = $this->cmsUser(['manage users'], 'User Administrator');

        Livewire::actingAs($administrator);
        Livewire::test(UsersManager::class)->assertForbidden();
    }

    public function test_last_super_admin_cannot_be_demoted_or_deleted(): void
    {
        $administrator = $this->cmsUser(['manage users'], 'User Administrator');
        Role::findOrCreate('Editor', 'web');
        $superAdminRole = Role::findOrCreate('Super Admin', 'web');
        $lastSuperAdmin = User::factory()->create();
        $lastSuperAdmin->assignRole($superAdminRole);

        $this->actingAs($administrator)->withSession([
            'auth.password_confirmed_at' => time(),
        ]);

        Livewire::test(UsersManager::class)
            ->call('edit', $lastSuperAdmin->id)
            ->set('form.role', 'Editor')
            ->call('save')
            ->assertHasErrors('form.role');

        $this->assertTrue($lastSuperAdmin->refresh()->hasRole('Super Admin'));

        Livewire::test(UsersManager::class)
            ->call('delete', $lastSuperAdmin->id)
            ->assertStatus(422);

        $this->assertModelExists($lastSuperAdmin);
        $this->assertSame(1, User::role('Super Admin')->count());
    }

    public function test_super_admin_cannot_change_their_own_role(): void
    {
        Role::findOrCreate('Editor', 'web');
        $superAdmin = $this->cmsUser(['manage users'], 'Super Admin');

        $this->actingAs($superAdmin)->withSession([
            'auth.password_confirmed_at' => time(),
        ]);

        Livewire::test(UsersManager::class)
            ->call('edit', $superAdmin->id)
            ->set('form.role', 'Editor')
            ->call('save')
            ->assertHasErrors('form.role');

        $this->assertTrue($superAdmin->refresh()->hasRole('Super Admin'));
    }
}
