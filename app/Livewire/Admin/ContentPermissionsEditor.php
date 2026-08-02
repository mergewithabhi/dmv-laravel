<?php

namespace App\Livewire\Admin;

use App\Domain\Content\PageSchemaRegistry;
use App\Enums\FieldGroup;
use App\Models\ContentPermission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.admin')]
class ContentPermissionsEditor extends Component
{
    #[Locked]
    public int $userId;

    public array $grants = [];

    public function mount(User $user, PageSchemaRegistry $registry): void
    {
        $this->authorizeAccess();
        abort_if($user->hasRole('Super Admin'), 422, 'Super Admin already has unrestricted access.');
        $this->userId = $user->id;

        foreach ($registry->all() as $templateKey => $schema) {
            $this->grants[$templateKey] = [
                'all' => false,
                'sections' => collect($schema['sections'])->mapWithKeys(fn ($section, $sectionKey) => [
                    $sectionKey => [
                        'all' => false,
                        'groups' => collect(FieldGroup::cases())->mapWithKeys(
                            fn (FieldGroup $group) => [$group->value => false]
                        )->all(),
                    ],
                ])->all(),
            ];
        }

        foreach ($user->contentPermissions as $grant) {
            if (! isset($this->grants[$grant->template_key]) && $grant->template_key !== ContentPermission::WILDCARD) {
                continue;
            }

            if ($grant->template_key === ContentPermission::WILDCARD) {
                foreach (array_keys($this->grants) as $templateKey) {
                    $this->grants[$templateKey]['all'] = true;
                }

                continue;
            }

            if ($grant->section_key === ContentPermission::WILDCARD) {
                $this->grants[$grant->template_key]['all'] = true;

                continue;
            }

            if (! isset($this->grants[$grant->template_key]['sections'][$grant->section_key])) {
                continue;
            }

            if ($grant->field_group === ContentPermission::WILDCARD) {
                $this->grants[$grant->template_key]['sections'][$grant->section_key]['all'] = true;

                continue;
            }

            if (isset($this->grants[$grant->template_key]['sections'][$grant->section_key]['groups'][$grant->field_group])) {
                $this->grants[$grant->template_key]['sections'][$grant->section_key]['groups'][$grant->field_group] = true;
            }
        }
    }

    public function save(): void
    {
        $this->authorizeAccess();
        $user = User::query()->findOrFail($this->userId);
        abort_if($user->hasRole('Super Admin'), 422, 'Super Admin already has unrestricted access.');

        $rows = [];
        foreach ($this->grants as $templateKey => $page) {
            if ($page['all']) {
                $rows[] = [$templateKey, ContentPermission::WILDCARD, ContentPermission::WILDCARD];

                continue;
            }

            foreach ($page['sections'] as $sectionKey => $section) {
                if ($section['all']) {
                    $rows[] = [$templateKey, $sectionKey, ContentPermission::WILDCARD];

                    continue;
                }

                foreach ($section['groups'] as $group => $enabled) {
                    if ($enabled) {
                        $rows[] = [$templateKey, $sectionKey, $group];
                    }
                }
            }
        }

        DB::transaction(function () use ($user, $rows): void {
            $user->contentPermissions()->delete();
            foreach ($rows as [$templateKey, $sectionKey, $fieldGroup]) {
                $user->contentPermissions()->create([
                    'template_key' => $templateKey,
                    'section_key' => $sectionKey,
                    'field_group' => $fieldGroup,
                ]);
            }

            if ($rows !== [] && ! $user->can('access admin')) {
                $user->givePermissionTo('access admin');
            }
        });

        activity('security')
            ->causedBy(auth()->user())
            ->performedOn($user)
            ->withProperties(['grants' => count($rows)])
            ->log('updated content permissions');

        session()->flash('success', 'Content permissions were updated.');
    }

    public function render()
    {
        $this->authorizeAccess();
        $user = User::query()->findOrFail($this->userId);

        return view('livewire.admin.content-permissions-editor', [
            'targetUser' => $user,
            'fieldGroups' => FieldGroup::cases(),
        ])->title('Content permissions for '.$user->name)
            ->layoutData(['heading' => 'Content permissions']);
    }

    private function authorizeAccess(): void
    {
        abort_unless(auth()->user()?->hasRole('Super Admin'), 403);
    }
}
