<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

#[Layout('components.layouts.admin')]
class UsersManager extends Component
{
    use WithPagination;

    #[Locked]
    public ?int $editingId = null;

    public bool $showEditor = false;

    public array $form = ['name' => '', 'email' => '', 'password' => '', 'role' => 'Editor'];

    public string $search = '';

    public string $roleFilter = '';

    public string $twoFactorFilter = '';

    public string $sortField = 'name';

    public string $sortDirection = 'asc';

    public int $perPage = 15;

    public array $selected = [];

    public function mount(): void
    {
        $this->authorizeSensitiveAccess();
    }

    public function updatedSearch(): void
    {
        $this->authorizeSensitiveAccess();
        $this->selected = [];
        $this->resetValidation('selected');
        $this->resetPage();
    }

    public function updatedRoleFilter(): void
    {
        $this->authorizeSensitiveAccess();
        $this->selected = [];
        $this->resetValidation('selected');
        $this->resetPage();
    }

    public function updatedTwoFactorFilter(): void
    {
        $this->authorizeSensitiveAccess();
        $this->selected = [];
        $this->resetValidation('selected');
        $this->resetPage();
    }

    public function updatedSelected(): void
    {
        $this->authorizeSensitiveAccess();
        $this->resetValidation('selected');
    }

    public function updatedPerPage(): void
    {
        $this->authorizeSensitiveAccess();
        if (! in_array($this->perPage, [10, 15, 25, 50], true)) {
            $this->perPage = 15;
        }
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        $this->authorizeSensitiveAccess();
        abort_unless(in_array($field, $this->sortableFields(), true), 422);

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->authorizeSensitiveAccess();
        $this->reset(['search', 'roleFilter', 'twoFactorFilter']);
        $this->selected = [];
        $this->resetValidation('selected');
        $this->resetPage();
    }

    public function togglePageSelection(string $ids): void
    {
        $this->authorizeSensitiveAccess();
        $pageIds = collect(explode(',', $ids))
            ->filter(fn (string $id) => ctype_digit($id))
            ->map(fn (string $id) => (int) $id)
            ->values()
            ->all();
        $selected = collect($this->selected)->map(fn ($id) => (int) $id);

        if ($pageIds !== [] && collect($pageIds)->every(fn (int $id) => $selected->contains($id))) {
            $this->selected = $selected->reject(fn (int $id) => in_array($id, $pageIds, true))->values()->all();
            $this->resetValidation('selected');

            return;
        }

        $this->selected = $selected->merge($pageIds)->unique()->values()->all();
        $this->resetValidation('selected');
    }

    public function clearSelection(): void
    {
        $this->authorizeSensitiveAccess();
        $this->selected = [];
        $this->resetValidation('selected');
    }

    public function create(): void
    {
        $this->authorizeSensitiveAccess();
        $this->editingId = null;
        $this->form = ['name' => '', 'email' => '', 'password' => '', 'role' => 'Editor'];
        $this->showEditor = true;
        $this->resetValidation();
    }

    public function cancelEditor(): void
    {
        $this->authorizeSensitiveAccess();
        $this->editingId = null;
        $this->showEditor = false;
        $this->form = ['name' => '', 'email' => '', 'password' => '', 'role' => 'Editor'];
        $this->resetValidation();
    }

    public function edit(int $id): void
    {
        $this->authorizeSensitiveAccess();
        $user = User::query()->with('roles')->findOrFail($id);
        $this->editingId = $id;
        $this->form = [
            'name' => $user->name,
            'email' => $user->email,
            'password' => '',
            'role' => $user->roles->first()?->name ?: 'Editor',
        ];
        $this->showEditor = true;
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->authorizeSensitiveAccess();
        $validated = $this->validate([
            'form.name' => ['required', 'string', 'max:120'],
            'form.email' => ['required', 'email', 'max:254', 'unique:users,email,'.($this->editingId ?: 'NULL')],
            'form.password' => [$this->editingId ? 'nullable' : 'required', 'string', 'min:12'],
            'form.role' => ['required', 'exists:roles,name'],
        ])['form'];

        $user = $this->editingId
            ? User::query()->with('roles')->findOrFail($this->editingId)
            : new User;
        $currentRole = $user->exists ? $user->roles->first()?->name : null;
        $targetRole = Role::findByName($validated['role'], 'web');

        if (! auth()->user()->hasRole('Super Admin')) {
            $missingPermissions = $targetRole->permissions
                ->pluck('name')
                ->diff(auth()->user()->getAllPermissions()->pluck('name'));

            if ($targetRole->name === 'Super Admin' || $missingPermissions->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'form.role' => 'You cannot grant a role with permissions you do not have.',
                ]);
            }
        }

        if ($user->is(auth()->user()) && $currentRole !== $validated['role']) {
            throw ValidationException::withMessages([
                'form.role' => 'You cannot change your own role.',
            ]);
        }

        if (
            $user->exists
            && $currentRole === 'Super Admin'
            && $validated['role'] !== 'Super Admin'
            && $this->superAdminCount() <= 1
        ) {
            throw ValidationException::withMessages([
                'form.role' => 'The CMS must retain at least one Super Admin.',
            ]);
        }

        $passwordChanged = filled($validated['password']);
        $user->name = $validated['name'];
        $user->email = strtolower($validated['email']);
        if ($passwordChanged) {
            $user->password = Hash::make($validated['password']);
        }
        if (! $this->editingId) {
            $user->email_verified_at = now();
        }
        $user->save();
        $user->syncRoles([$validated['role']]);
        if ($passwordChanged) {
            $this->revokeUserSessions($user, $user->is(auth()->user()));
        }
        $event = $this->editingId ? 'updated user' : 'created user';
        activity('security')->causedBy(auth()->user())->performedOn($user)->log($event);
        $this->cancelEditor();
        session()->flash('success', 'CMS user saved.');
    }

    public function resetTwoFactor(int $id): void
    {
        $this->authorizeSensitiveAccess();
        $user = User::query()->findOrFail($id);
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
        $this->revokeUserSessions($user, $user->is(auth()->user()));
        activity('security')->causedBy(auth()->user())->performedOn($user)->log('reset two factor authentication');
        session()->flash('success', 'Two-factor authentication was reset.');
    }

    public function destroy(int $id): void
    {
        $this->authorizeSensitiveAccess();
        abort_if($id === auth()->id(), 422, 'You cannot delete your own account.');
        $user = User::query()->with('roles')->findOrFail($id);
        abort_if(
            $user->hasRole('Super Admin') && $this->superAdminCount() <= 1,
            422,
            'The CMS must retain at least one Super Admin.'
        );
        $this->revokeUserSessions($user);
        activity('security')->causedBy(auth()->user())->performedOn($user)->log('deleted user');
        $user->delete();
        $this->selected = collect($this->selected)
            ->reject(fn ($selectedId) => (int) $selectedId === $id)
            ->values()
            ->all();
        session()->flash('success', 'CMS user deleted.');
    }

    public function bulkDelete(): void
    {
        $this->authorizeSensitiveAccess();
        $ids = collect($this->selected)->map(fn ($id) => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            throw ValidationException::withMessages([
                'selected' => 'Select at least one user.',
            ]);
        }

        if ($ids->contains((int) auth()->id())) {
            throw ValidationException::withMessages([
                'selected' => 'Remove your own account from the selection before deleting users.',
            ]);
        }

        $users = User::query()->with('roles')->whereKey($ids)->get();
        if ($users->isEmpty()) {
            throw ValidationException::withMessages([
                'selected' => 'The selected users no longer exist.',
            ]);
        }

        $selectedSuperAdmins = $users->filter->hasRole('Super Admin')->count();
        if ($selectedSuperAdmins >= $this->superAdminCount()) {
            throw ValidationException::withMessages([
                'selected' => 'The CMS must retain at least one Super Admin.',
            ]);
        }

        DB::transaction(function () use ($users): void {
            $users->each(function (User $user): void {
                $this->revokeUserSessions($user);
                activity('security')->causedBy(auth()->user())->performedOn($user)->log('deleted user');
                $user->delete();
            });
        });

        $count = $users->count();
        $this->selected = [];
        session()->flash('success', "{$count} CMS ".str('user')->plural($count).' deleted.');
    }

    public function export()
    {
        $this->authorizeSensitiveAccess();
        $users = $this->usersQuery()
            ->when(
                $this->selected !== [],
                fn ($query) => $query->whereKey(collect($this->selected)->map(fn ($id) => (int) $id))
            )
            ->get();

        return response()->streamDownload(function () use ($users): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Name', 'Email', 'Role', 'Two-factor authentication', 'Created']);
            foreach ($users as $user) {
                fputcsv($output, [
                    $this->csvValue($user->name),
                    $this->csvValue($user->email),
                    $this->csvValue($user->roles->pluck('name')->join(', ')),
                    $user->two_factor_confirmed_at ? 'Enabled' : 'Not enabled',
                    $user->created_at->toIso8601String(),
                ]);
            }
            fclose($output);
        }, 'dmv-cms-users-'.now()->format('Y-m-d').'.csv');
    }

    public function render()
    {
        $this->authorizeSensitiveAccess();

        return view('livewire.admin.users-manager', [
            'users' => $this->usersQuery()->paginate($this->perPage),
            'roles' => Role::query()->orderBy('name')->get(),
        ])->title('Users')->layoutData(['heading' => 'Users and Roles']);
    }

    private function usersQuery()
    {
        $search = trim($this->search);
        $sortField = in_array($this->sortField, $this->sortableFields(), true)
            ? $this->sortField
            : 'name';
        $sortDirection = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        return User::query()
            ->with('roles')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->when(
                $this->roleFilter !== '',
                fn ($query) => $query->whereHas(
                    'roles',
                    fn ($query) => $query->where('name', $this->roleFilter)
                )
            )
            ->when(
                $this->twoFactorFilter === 'enabled',
                fn ($query) => $query->whereNotNull('two_factor_confirmed_at')
            )
            ->when(
                $this->twoFactorFilter === 'disabled',
                fn ($query) => $query->whereNull('two_factor_confirmed_at')
            )
            ->orderBy($sortField, $sortDirection)
            ->orderBy('id');
    }

    private function sortableFields(): array
    {
        return ['name', 'email', 'two_factor_confirmed_at', 'created_at'];
    }

    private function csvValue(?string $value): string
    {
        $value ??= '';

        return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
    }

    private function authorizeSensitiveAccess(): void
    {
        abort_unless(auth()->user()?->can('manage users'), 403);

        $confirmedAt = (int) session('auth.password_confirmed_at', 0);
        abort_unless(
            $confirmedAt > 0 && (time() - $confirmedAt) <= (int) config('auth.password_timeout'),
            403,
            'Recent password confirmation is required.'
        );
    }

    private function superAdminCount(): int
    {
        return User::role('Super Admin')->count();
    }

    private function revokeUserSessions(User $user, bool $keepCurrent = false): void
    {
        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();

        if (config('session.driver') !== 'database' || ! Schema::hasTable(config('session.table'))) {
            return;
        }

        $query = DB::table(config('session.table'))->where('user_id', $user->getKey());
        if ($keepCurrent) {
            $query->where('id', '!=', session()->getId());
        }
        $query->delete();
    }
}
