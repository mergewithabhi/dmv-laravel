<div>
    <div class="admin-page-heading">
        <div>
            <h2>Newsletter subscribers</h2>
            <p>Review consent, provider synchronization, and subscription status.</p>
        </div>
        @can('export submissions')
            <button class="admin-button secondary" type="button" wire:click="export">
                Export filtered CSV
            </button>
        @endcan
    </div>

    <section class="admin-panel">
        <div class="admin-panel-body">
            <div class="admin-toolbar" aria-label="Newsletter subscriber filters">
                <div class="admin-filter admin-filter-grow">
                    <label for="subscriber-search">Search subscribers</label>
                    <input
                        id="subscriber-search"
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Exact email, UUID, or provider"
                    >
                </div>
                <div class="admin-filter">
                    <label for="subscriber-status">Status</label>
                    <select id="subscriber-status" wire:model.live="statusFilter">
                        <option value="">All statuses</option>
                        <option value="pending">Pending</option>
                        <option value="failed">Failed</option>
                        <option value="subscribed">Subscribed</option>
                        <option value="unsubscribed">Unsubscribed</option>
                    </select>
                </div>
                <div class="admin-filter admin-filter-compact">
                    <label for="subscriber-per-page">Rows</label>
                    <select id="subscriber-per-page" wire:model.live="perPage">
                        @foreach ([10, 15, 25, 50] as $size)
                            <option value="{{ $size }}">{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($search !== '' || $statusFilter !== '')
                    <button class="admin-button secondary admin-filter-action" type="button" wire:click="resetFilters">
                        Clear filters
                    </button>
                @endif
            </div>

            <div class="admin-list-summary">
                <span>{{ number_format($subscribers->total()) }} {{ str('subscriber')->plural($subscribers->total()) }}</span>
                <span wire:loading wire:target="search,statusFilter,perPage,sortBy">Updating...</span>
            </div>
        </div>

        <div class="admin-table-wrap">
            <table class="admin-table admin-table-list">
                <caption class="sr-only">Newsletter subscribers</caption>
                <thead>
                <tr>
                    <th>Email</th>
                    <th aria-sort="{{ $sortField === 'status' ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                        <button class="admin-sort-button" type="button" wire:click="sortBy('status')">
                            Status
                            <span class="admin-sort-indicator {{ $sortField === 'status' ? $sortDirection : '' }}" aria-hidden="true"></span>
                        </button>
                    </th>
                    <th aria-sort="{{ $sortField === 'provider' ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                        <button class="admin-sort-button" type="button" wire:click="sortBy('provider')">
                            Provider
                            <span class="admin-sort-indicator {{ $sortField === 'provider' ? $sortDirection : '' }}" aria-hidden="true"></span>
                        </button>
                    </th>
                    <th>Consent</th>
                    <th aria-sort="{{ $sortField === 'last_synced_at' ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                        <button class="admin-sort-button" type="button" wire:click="sortBy('last_synced_at')">
                            Last sync
                            <span class="admin-sort-indicator {{ $sortField === 'last_synced_at' ? $sortDirection : '' }}" aria-hidden="true"></span>
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
                @forelse ($subscribers as $subscriber)
                    @php
                        $displayStatus = filled($subscriber->last_error) ? 'failed' : $subscriber->status;
                        $statusClass = match ($displayStatus) {
                            'subscribed' => 'published',
                            'pending' => 'in_review',
                            default => $displayStatus,
                        };
                    @endphp
                    <tr wire:key="newsletter-subscriber-{{ $subscriber->id }}">
                        <td>
                            <a class="admin-break-text" href="mailto:{{ $subscriber->email }}">{{ $subscriber->email }}</a>
                            <small class="admin-table-secondary admin-break-text">{{ $subscriber->uuid }}</small>
                        </td>
                        <td><span class="status-badge {{ $statusClass }}">{{ ucfirst($displayStatus) }}</span></td>
                        <td>
                            {{ ucfirst($subscriber->provider) }}
                            @if ($subscriber->provider_id)
                                <small class="admin-table-secondary admin-break-text">{{ $subscriber->provider_id }}</small>
                            @endif
                        </td>
                        <td>{{ $subscriber->consent ? 'Recorded' : 'Not recorded' }}</td>
                        <td>
                            {{ $subscriber->last_synced_at?->format('M j, Y g:i A') ?? 'Never' }}
                            @if ($subscriber->last_error)
                                <small class="admin-table-secondary admin-break-text">{{ $subscriber->last_error }}</small>
                            @endif
                        </td>
                        <td>{{ $subscriber->created_at->format('M j, Y g:i A') }}</td>
                        <td>
                            <div class="admin-actions">
                                @if ($subscriber->status !== 'unsubscribed')
                                    <button
                                        class="admin-button secondary small"
                                        type="button"
                                        wire:click="retrySync({{ $subscriber->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="retrySync({{ $subscriber->id }})"
                                    >Retry sync</button>
                                    <button
                                        class="admin-button secondary small"
                                        type="button"
                                        wire:click="unsubscribe({{ $subscriber->id }})"
                                        data-confirm-title="Unsubscribe contact?"
                                        data-confirm-message="This contact will no longer be synchronized with the newsletter provider."
                                        data-confirm-button="Unsubscribe"
                                        data-confirm-variant="warning"
                                    >Unsubscribe</button>
                                @endif
                                <button
                                    class="admin-button danger small"
                                    type="button"
                                    wire:click="delete({{ $subscriber->id }})"
                                    data-confirm-title="Delete subscriber?"
                                    data-confirm-message="Permanently delete this newsletter subscriber? This cannot be undone."
                                    data-confirm-button="Delete subscriber"
                                >Delete</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="admin-empty">No newsletter subscribers match the current filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @include('livewire.admin.partials.pagination', ['paginator' => $subscribers])
    </section>
</div>
