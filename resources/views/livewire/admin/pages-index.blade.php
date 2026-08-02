<div>
    @php
        $pageIds = $pages->pluck('id')->map(fn ($id) => (int) $id);
        $selectedIds = collect($selected)->map(fn ($id) => (int) $id);
        $allPageSelected = $pageIds->isNotEmpty() && $pageIds->every(fn ($id) => $selectedIds->contains($id));
    @endphp

    <div class="admin-page-heading">
        <div>
            <h2>Fixed page templates</h2>
            <p>Edit every public content field without changing approved layouts.</p>
        </div>
        <button class="admin-button secondary" type="button" wire:click="export">
            Export filtered CSV
        </button>
    </div>

    <section class="admin-panel">
        <div class="admin-panel-body">
            <div class="admin-toolbar" aria-label="Page filters">
                <div class="admin-filter admin-filter-grow">
                    <label for="page-search">Search pages</label>
                    <input id="page-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Title, URL, or template">
                </div>
                <div class="admin-filter">
                    <label for="page-status">Workflow status</label>
                    <select id="page-status" wire:model.live="statusFilter">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="admin-filter admin-filter-compact">
                    <label for="page-per-page">Rows</label>
                    <select id="page-per-page" wire:model.live="perPage">
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
                <span>{{ number_format($pages->total()) }} {{ str('page')->plural($pages->total()) }}</span>
                <span wire:loading wire:target="search,statusFilter,perPage,sortBy">Updating...</span>
            </div>

            @if ($selected)
                <div class="admin-bulk-bar" aria-label="Bulk page actions">
                    <strong>{{ count($selected) }} selected</strong>
                    <div class="admin-actions">
                        <button class="admin-button secondary small" type="button" wire:click="bulkSubmit" wire:loading.attr="disabled">Submit drafts</button>
                        @can('publish content')
                            <button class="admin-button small" type="button" wire:click="bulkPublish" wire:loading.attr="disabled">Approve reviews</button>
                        @endcan
                        <button class="admin-button secondary small" type="button" wire:click="clearSelection">Clear selection</button>
                    </div>
                </div>
            @endif
            @error('selected')<div class="admin-alert error" role="alert">{{ $message }}</div>@enderror
        </div>

        <div class="admin-table-wrap">
            <table class="admin-table admin-table-list">
                <caption class="sr-only">Fixed website pages</caption>
                <thead>
                <tr>
                    <th class="admin-select-column">
                        <input
                            type="checkbox"
                            aria-label="Select all pages on this page"
                            wire:click="togglePageSelection('{{ $pageIds->implode(',') }}')"
                            @checked($allPageSelected)
                            @disabled($pageIds->isEmpty())
                        >
                    </th>
                    @foreach ([
                        'title' => 'Page',
                        'template_key' => 'Template',
                        'sections_count' => 'Sections',
                        'workflow_status' => 'Status',
                        'updated_at' => 'Updated',
                    ] as $field => $label)
                        <th aria-sort="{{ $sortField === $field ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                            <button class="admin-sort-button" type="button" wire:click="sortBy('{{ $field }}')">
                                {{ $label }}
                                <span class="admin-sort-indicator {{ $sortField === $field ? $sortDirection : '' }}" aria-hidden="true"></span>
                            </button>
                        </th>
                    @endforeach
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($pages as $page)
                    <tr wire:key="page-{{ $page->id }}">
                        <td class="admin-select-column">
                            <input type="checkbox" value="{{ $page->id }}" wire:model.live="selected" aria-label="Select {{ $page->title }}">
                        </td>
                        <td>
                            <strong>{{ $page->title }}</strong>
                            <small class="admin-table-secondary">/{{ $page->slug === 'home' ? '' : $page->slug }}</small>
                        </td>
                        <td>{{ $page->template_key }}</td>
                        <td>{{ $page->sections_count }}</td>
                        <td><span class="status-badge {{ $page->workflow_status->value }}">{{ $page->workflow_status->label() }}</span></td>
                        <td>{{ $page->updated_at->diffForHumans() }}</td>
                        <td>
                            <div class="admin-actions">
                                <a class="admin-button secondary small" href="{{ route('admin.pages.edit', $page) }}" wire:navigate>Edit</a>
                                <a class="admin-button secondary small" href="{{ $this->previewUrl($page) }}" target="_blank" rel="noopener">Preview</a>
                                @if ($page->workflow_status->value === 'draft' && $page->draft_snapshot)
                                    <button class="admin-button small" type="button" wire:click="submit({{ $page->id }})">Submit</button>
                                @endif
                                @can('publish content')
                                    @if ($page->workflow_status->value === 'in_review')
                                        <button class="admin-button small" type="button" wire:click="publish({{ $page->id }})">Approve</button>
                                    @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="admin-empty">No pages match the current filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @include('livewire.admin.partials.pagination', ['paginator' => $pages])
    </section>
</div>
