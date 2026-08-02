<div>
    @php
        $userIds = $users->where('id', '!=', auth()->id())->pluck('id')->map(fn ($id) => (int) $id);
        $selectedIds = collect($selected)->map(fn ($id) => (int) $id);
        $allPageSelected = $userIds->isNotEmpty() && $userIds->every(fn ($id) => $selectedIds->contains($id));
    @endphp

    <div class="admin-page-heading">
        <div>
            <h2>CMS users</h2>
            <p>Manage access, roles, passwords, and two-factor recovery.</p>
        </div>
        <div class="admin-actions">
            <button class="admin-button secondary" type="button" wire:click="export">
                {{ $selected ? 'Export selected' : 'Export filtered CSV' }}
            </button>
            <button class="admin-button" type="button" wire:click="create" data-admin-focus-target="#user-editor">Add user</button>
        </div>
    </div>

    @if ($showEditor)
        <section
            id="user-editor"
            class="admin-panel section-editor"
            tabindex="-1"
            aria-labelledby="user-editor-heading"
            data-admin-action-area
        >
            <div class="admin-panel-header">
                <h3 id="user-editor-heading">{{ $editingId ? 'Edit user' : 'New user' }}</h3>
                <button class="admin-button secondary small" type="button" wire:click="cancelEditor">Close</button>
            </div>
            <form class="admin-panel-body admin-form-grid" wire:submit="save">
                <div class="admin-field">
                    <label for="user-name">Name</label>
                    <input id="user-name" autocomplete="name" wire:model="form.name" @error('form.name') aria-invalid="true" aria-describedby="user-name-error" @enderror>
                    @error('form.name')<span id="user-name-error" class="admin-field-error">{{ $message }}</span>@enderror
                </div>
                <div class="admin-field">
                    <label for="user-email">Email</label>
                    <input id="user-email" type="email" autocomplete="email" wire:model="form.email" @error('form.email') aria-invalid="true" aria-describedby="user-email-error" @enderror>
                    @error('form.email')<span id="user-email-error" class="admin-field-error">{{ $message }}</span>@enderror
                </div>
                <div class="admin-field">
                    <label for="user-password">{{ $editingId ? 'New password (optional)' : 'Password' }}</label>
                    <input id="user-password" type="password" autocomplete="new-password" wire:model="form.password" @error('form.password') aria-invalid="true" aria-describedby="user-password-error" @enderror>
                    @error('form.password')<span id="user-password-error" class="admin-field-error">{{ $message }}</span>@enderror
                </div>
                <div class="admin-field">
                    <label for="user-role">Role</label>
                    <select id="user-role" wire:model="form.role" @error('form.role') aria-invalid="true" aria-describedby="user-role-error" @enderror>
                        @foreach ($roles as $role)
                            <option value="{{ $role->name }}">{{ $role->name }}</option>
                        @endforeach
                    </select>
                    @error('form.role')<span id="user-role-error" class="admin-field-error">{{ $message }}</span>@enderror
                </div>
                <div class="admin-actions">
                    <button class="admin-button" type="submit">Save user</button>
                    <button class="admin-button secondary" type="button" wire:click="cancelEditor">Cancel</button>
                </div>
            </form>
        </section>
    @endif

    <section class="admin-panel">
        <div class="admin-panel-body">
            <div class="admin-toolbar" aria-label="User filters">
                <div class="admin-filter admin-filter-grow">
                    <label for="user-search">Search users</label>
                    <input id="user-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Name or email">
                </div>
                <div class="admin-filter">
                    <label for="user-role-filter">Role</label>
                    <select id="user-role-filter" wire:model.live="roleFilter">
                        <option value="">All roles</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->name }}">{{ $role->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="admin-filter">
                    <label for="user-2fa-filter">Two-factor authentication</label>
                    <select id="user-2fa-filter" wire:model.live="twoFactorFilter">
                        <option value="">Any 2FA status</option>
                        <option value="enabled">Enabled</option>
                        <option value="disabled">Not enabled</option>
                    </select>
                </div>
                <div class="admin-filter admin-filter-compact">
                    <label for="user-per-page">Rows</label>
                    <select id="user-per-page" wire:model.live="perPage">
                        @foreach ([10, 15, 25, 50] as $size)
                            <option value="{{ $size }}">{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($search !== '' || $roleFilter !== '' || $twoFactorFilter !== '')
                    <button class="admin-button secondary admin-filter-action" type="button" wire:click="resetFilters">Clear filters</button>
                @endif
            </div>

            <div class="admin-list-summary">
                <span>{{ number_format($users->total()) }} {{ str('user')->plural($users->total()) }}</span>
                <span wire:loading wire:target="search,roleFilter,twoFactorFilter,perPage,sortBy">Updating...</span>
            </div>

            @if ($selected)
                <div class="admin-bulk-bar" aria-label="Bulk user actions">
                    <strong>{{ count($selected) }} selected</strong>
                    <div class="admin-actions">
                        <button
                            class="admin-button danger small"
                            type="button"
                            wire:click="bulkDelete"
                            data-confirm-title="Delete selected users?"
                            data-confirm-message="The selected CMS users will be permanently deleted and their active sessions revoked."
                            data-confirm-button="Delete users"
                        >Delete selected</button>
                        <button class="admin-button secondary small" type="button" wire:click="clearSelection">Clear selection</button>
                    </div>
                </div>
            @endif
            @error('selected')<div class="admin-alert error" role="alert">{{ $message }}</div>@enderror
        </div>

        <div class="admin-table-wrap">
            <table class="admin-table admin-table-list">
                <caption class="sr-only">CMS user accounts</caption>
                <thead>
                <tr>
                    <th class="admin-select-column">
                        <input
                            type="checkbox"
                            aria-label="Select all deletable users on this page"
                            wire:click="togglePageSelection('{{ $userIds->implode(',') }}')"
                            @checked($allPageSelected)
                            @disabled($userIds->isEmpty())
                        >
                    </th>
                    @foreach ([
                        'name' => 'Name',
                        'email' => 'Email',
                    ] as $field => $label)
                        <th aria-sort="{{ $sortField === $field ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                            <button class="admin-sort-button" type="button" wire:click="sortBy('{{ $field }}')">
                                {{ $label }}
                                <span class="admin-sort-indicator {{ $sortField === $field ? $sortDirection : '' }}" aria-hidden="true"></span>
                            </button>
                        </th>
                    @endforeach
                    <th>Role</th>
                    <th aria-sort="{{ $sortField === 'two_factor_confirmed_at' ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                        <button class="admin-sort-button" type="button" wire:click="sortBy('two_factor_confirmed_at')">
                            2FA
                            <span class="admin-sort-indicator {{ $sortField === 'two_factor_confirmed_at' ? $sortDirection : '' }}" aria-hidden="true"></span>
                        </button>
                    </th>
                    <th aria-sort="{{ $sortField === 'created_at' ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                        <button class="admin-sort-button" type="button" wire:click="sortBy('created_at')">
                            Added
                            <span class="admin-sort-indicator {{ $sortField === 'created_at' ? $sortDirection : '' }}" aria-hidden="true"></span>
                        </button>
                    </th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($users as $user)
                    <tr wire:key="user-{{ $user->id }}">
                        <td class="admin-select-column">
                            <input
                                type="checkbox"
                                value="{{ $user->id }}"
                                wire:model.live="selected"
                                aria-label="Select {{ $user->name }}"
                                @disabled($user->id === auth()->id())
                            >
                        </td>
                        <td>
                            <strong>{{ $user->name }}</strong>
                            @if ($user->id === auth()->id())<small class="admin-table-secondary">Current account</small>@endif
                        </td>
                        <td><a href="mailto:{{ $user->email }}">{{ $user->email }}</a></td>
                        <td>{{ $user->roles->pluck('name')->join(', ') ?: 'No role' }}</td>
                        <td>
                            <span class="status-badge {{ $user->two_factor_confirmed_at ? 'published' : '' }}">
                                {{ $user->two_factor_confirmed_at ? 'Enabled' : 'Not enabled' }}
                            </span>
                        </td>
                        <td>{{ $user->created_at->format('M j, Y') }}</td>
                        <td>
                            <div class="admin-actions">
                                <button
                                    class="admin-button secondary small"
                                    type="button"
                                    wire:click="edit({{ $user->id }})"
                                    data-admin-focus-target="#user-editor"
                                >Edit</button>
                                @unless ($user->hasRole('Super Admin'))
                                    <a class="admin-button secondary small" href="{{ route('admin.users.content-permissions', $user) }}" wire:navigate>Content permissions</a>
                                @endunless
                                @if ($user->two_factor_confirmed_at)
                                    <button
                                        class="admin-button secondary small"
                                        type="button"
                                        wire:click="resetTwoFactor({{ $user->id }})"
                                        data-confirm-title="Reset two-factor authentication?"
                                        data-confirm-message="The user's two-factor setup will be removed and their other sessions revoked."
                                        data-confirm-button="Reset 2FA"
                                        data-confirm-variant="warning"
                                    >Reset 2FA</button>
                                @endif
                                @if ($user->id !== auth()->id())
                                    <button
                                        class="admin-button danger small"
                                        type="button"
                                        wire:click="delete({{ $user->id }})"
                                        data-confirm-title="Delete CMS user?"
                                        data-confirm-message="This user will be permanently deleted and their active sessions revoked."
                                        data-confirm-button="Delete user"
                                    >Delete</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="admin-empty">No users match the current filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @include('livewire.admin.partials.pagination', ['paginator' => $users])
    </section>
</div>
