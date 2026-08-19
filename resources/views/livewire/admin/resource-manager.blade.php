<div>
    @php
        $rowIds = $rows->getCollection()->map(fn ($row) => (int) $row->getKey());
        $selectedIds = collect($selected)->map(fn ($id) => (int) $id);
        $allPageSelected = $rowIds->isNotEmpty() && $rowIds->every(fn ($id) => $selectedIds->contains($id));
    @endphp

    <div class="admin-page-heading">
        <div>
            <h2>{{ $config['label'] }}</h2>
            <p>Search, edit, publish, and export structured website content.</p>
        </div>
        <button class="admin-button" type="button" wire:click="newRecord" data-admin-focus-target="#resource-editor">Add record</button>
    </div>

    @if ($showEditor)
        <section
            id="resource-editor"
            class="admin-panel section-editor"
            tabindex="-1"
            aria-labelledby="resource-editor-heading"
            data-admin-action-area
        >
            <div class="admin-panel-header">
                <h3 id="resource-editor-heading">{{ $editingId ? 'Edit record' : 'New record' }}</h3>
                <button class="admin-button secondary small" type="button" wire:click="$set('showEditor', false)">Close</button>
            </div>
            <form class="admin-panel-body admin-form-grid" wire:submit="save">
                @foreach ($config['fields'] as $key => $field)
                    @continue($key === 'publish_at' && data_get($form, $config['status_field'] ?? '') !== 'scheduled')
                    @continue(in_array($key, ['slug', 'status', 'publication_status', 'publish_at', 'timezone', 'seo_title', 'seo_description', 'position_order'], true))
                    @include('livewire.admin.partials.resource-field')
                @endforeach
                @php
                    $advancedKeys = ['slug', 'status', 'publication_status', 'publish_at', 'timezone', 'seo_title', 'seo_description', 'position_order'];
                    $advancedFields = collect($config['fields'])->only($advancedKeys);
                @endphp
                @if ($advancedFields->isNotEmpty())
                    <details class="admin-field full resource-advanced">
                        <summary>Advanced settings</summary>
                        <div class="admin-form-grid">
                            @foreach ($advancedFields as $key => $field)
                                @continue($key === 'publish_at' && data_get($form, $config['status_field'] ?? '') !== 'scheduled')
                                @include('livewire.admin.partials.resource-field')
                            @endforeach
                        </div>
                    </details>
                @endif
                @error('form')<div class="admin-field full admin-field-error">{{ $message }}</div>@enderror
                <div class="admin-actions">
                    <button class="admin-button" type="submit">Save record</button>
                    <button class="admin-button secondary" type="button" wire:click="$set('showEditor', false)">Cancel</button>
                </div>
            </form>
            @if ($editingId && $revisions->isNotEmpty())
                <div class="admin-panel-body">
                    <h4>Recent revisions</h4>
                    <div class="admin-revision-list">
                        @foreach ($revisions as $revision)
                            <div class="admin-revision-row" wire:key="revision-{{ $revision->id }}">
                                <span>
                                    <strong>Version {{ $revision->version }}</strong>
                                    {{ \Illuminate\Support\Str::headline($revision->event) }}
                                    by {{ $revision->user?->name ?? 'System' }}
                                    <small>{{ $revision->created_at->diffForHumans() }}</small>
                                </span>
                                <button
                                    class="admin-button secondary small"
                                    type="button"
                                    wire:click="restoreRevision({{ $revision->id }})"
                                    data-confirm-title="Restore version {{ $revision->version }}?"
                                    data-confirm-message="Current values will be preserved as a new revision."
                                    data-confirm-phrase="RESTORE"
                                    data-confirm-button="Restore version"
                                    data-confirm-variant="warning"
                                >Restore</button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>
    @endif

    <section class="admin-panel">
        <div class="admin-panel-header">
            <h3>{{ $statusFilter === 'trashed' ? 'Trash' : 'All records' }}</h3>
            <button class="admin-button secondary small" type="button" wire:click="export">Export CSV</button>
        </div>
        <div class="admin-panel-body">
            <div class="admin-toolbar" aria-label="{{ $config['label'] }} filters">
                <div class="admin-filter admin-filter-grow">
                    <label for="resource-search">Search records</label>
                    <input id="resource-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Search {{ strtolower($config['label']) }}">
                </div>
                <div class="admin-filter">
                    <label for="resource-status">{{ isset($config['status_field']) ? 'Publication status' : 'Records' }}</label>
                    <select id="resource-status" wire:model.live="statusFilter">
                        <option value="">{{ isset($config['status_field']) ? 'All statuses' : 'All records' }}</option>
                        @if (isset($config['status_field']))
                            @foreach ($statusOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        @endif
                        <option value="trashed">Trash</option>
                    </select>
                </div>
                <div class="admin-filter admin-filter-compact">
                    <label for="resource-per-page">Rows</label>
                    <select id="resource-per-page" wire:model.live="perPage">
                        @foreach ([10, 15, 25, 50] as $size)
                            <option value="{{ $size }}">{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($search !== '' || $statusFilter !== '')
                    <button class="admin-button secondary admin-filter-action" type="button" wire:click="resetFilters">Clear filters</button>
                @endif
            </div>

            <div class="admin-list-summary">
                <span>{{ number_format($rows->total()) }} {{ str('record')->plural($rows->total()) }}</span>
                <span wire:loading wire:target="search,statusFilter,perPage,sortBy">Updating...</span>
            </div>

            @if ($selected)
                <div class="admin-bulk-bar" aria-label="Bulk record actions">
                    <strong>{{ count($selected) }} selected</strong>
                    <div class="admin-actions">
                        @if (isset($config['status_field']) && $statusFilter !== 'trashed')
                            <button class="admin-button small" type="button" wire:click="bulkPublish">Publish selected drafts</button>
                        @endif
                        <button class="admin-button secondary small" type="button" wire:click="clearSelection">Clear selection</button>
                    </div>
                </div>
            @endif
            @error('selected')<div class="admin-alert error" role="alert">{{ $message }}</div>@enderror
        </div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                <tr>
                    <th class="admin-select-column">
                        <input
                            type="checkbox"
                            aria-label="Select all records on this page"
                            wire:click="togglePageSelection('{{ $rowIds->implode(',') }}')"
                            @checked($allPageSelected)
                            @disabled($rowIds->isEmpty())
                        >
                    </th>
                    @foreach ($config['columns'] as $key => $label)
                        <th>
                            @if (!str_contains($key, '.'))
                                <button type="button" wire:click="sortBy('{{ $key }}')">{{ $label }}</button>
                            @else
                                {{ $label }}
                            @endif
                        </th>
                    @endforeach
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr wire:key="resource-{{ $resource }}-{{ $row->getKey() }}">
                        <td class="admin-select-column"><input type="checkbox" value="{{ $row->getKey() }}" wire:model.live="selected" aria-label="Select {{ $this->displayValue(data_get($row, array_key_first($config['columns']))) }}"></td>
                        @foreach (array_keys($config['columns']) as $key)
                            @php($value = data_get($row, $key))
                            <td>
                                @if ($row->trashed() && str_contains($key, 'status'))
                                    <span class="status-badge archived">Trashed</span>
                                @elseif (str_contains($key, 'status'))
                                    <span class="status-badge {{ $value instanceof \BackedEnum ? $value->value : $value }}">{{ $this->displayValue($value) }}</span>
                                @else
                                    {{ $this->displayValue($value) }}
                                @endif
                            </td>
                        @endforeach
                        <td>
                            <div class="admin-actions">
                                @if ($row->trashed())
                                    <button class="admin-button secondary small" type="button" wire:click="restore({{ $row->getKey() }})">Restore</button>
                                @else
                                    <button
                                        class="admin-button secondary small"
                                        type="button"
                                        wire:click="edit({{ $row->getKey() }})"
                                        data-admin-focus-target="#resource-editor"
                                    >Edit</button>
                                    <button
                                        class="admin-button danger small"
                                        type="button"
                                        wire:click="destroy({{ $row->getKey() }})"
                                        data-confirm-title="Move record to Trash?"
                                        data-confirm-message="The record will be hidden from the website and can be restored later."
                                        data-confirm-button="Move to Trash"
                                        data-confirm-variant="warning"
                                    >Delete</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count($config['columns']) + 2 }}" class="admin-empty">No records found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @include('livewire.admin.partials.pagination', ['paginator' => $rows])
    </section>
</div>
