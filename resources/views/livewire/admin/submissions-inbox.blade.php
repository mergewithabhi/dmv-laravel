<div>
    <div class="admin-page-heading">
        <div>
            <h2>Submissions inbox</h2>
            <p>Contact and sponsor inquiries retained under the configured privacy policy.</p>
        </div>
        @can('export submissions')
            <button class="admin-button secondary" type="button" wire:click="export">Export filtered CSV</button>
        @endcan
    </div>

    <div class="admin-inbox {{ $selected ? 'has-detail' : '' }}">
        <section class="admin-panel admin-inbox-list">
            <div class="admin-panel-body">
                <div class="admin-toolbar" aria-label="Submission filters">
                    <div class="admin-filter admin-filter-grow">
                        <label for="submission-search">Search submissions</label>
                        <input id="submission-search" type="search" wire:model.live.debounce.300ms="search" placeholder="UUID or exact email">
                    </div>
                    <div class="admin-filter">
                        <label for="submission-type">Type</label>
                        <select id="submission-type" wire:model.live="typeFilter">
                            <option value="">All types</option>
                            <option value="contact">Contact</option>
                            <option value="sponsor">Sponsor</option>
                        </select>
                    </div>
                </div>
                <div class="admin-list-summary">
                    <span>{{ number_format($submissions->total()) }} {{ str('submission')->plural($submissions->total()) }}</span>
                    <span wire:loading wire:target="search,typeFilter">Updating...</span>
                </div>
            </div>

            <div class="admin-table-wrap">
                <table class="admin-table admin-table-list">
                    <caption class="sr-only">Contact and sponsor submissions</caption>
                    <thead>
                    <tr><th>Received</th><th>Type</th><th>From</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                    @forelse ($submissions as $submission)
                        <tr wire:key="submission-{{ $submission->id }}" @class(['is-selected' => $selected?->id === $submission->id])>
                            <td>{{ $submission->created_at->format('M j, Y g:i A') }}</td>
                            <td>{{ ucfirst($submission->type) }}</td>
                            <td>{{ $submission->name ?: 'Not provided' }}</td>
                            <td>
                                <div class="admin-actions">
                                    <button
                                        class="admin-button secondary small"
                                        type="button"
                                        wire:click="select({{ $submission->id }})"
                                        data-admin-focus-target="#submission-detail"
                                    >Review</button>
                                    <button
                                        class="admin-button danger small"
                                        type="button"
                                        wire:click="destroy({{ $submission->id }})"
                                        data-confirm-title="Delete submission?"
                                        data-confirm-message="Permanently delete this submission? This cannot be undone."
                                        data-confirm-button="Delete submission"
                                    >Delete</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="admin-empty">No submissions match these filters.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @include('livewire.admin.partials.pagination', ['paginator' => $submissions])
        </section>

        @if ($selected)
            <aside
                id="submission-detail"
                class="admin-panel admin-inbox-detail"
                tabindex="-1"
                aria-labelledby="submission-detail-heading"
                data-admin-action-area
            >
                <div class="admin-panel-header">
                    <h3 id="submission-detail-heading">Submission <span class="admin-break-text">{{ $selected->uuid }}</span></h3>
                    <button class="admin-button secondary small" type="button" wire:click="closeSelection" aria-label="Close submission details">Close</button>
                </div>
                <div class="admin-panel-body admin-inbox-detail-body">
                    <dl class="admin-detail-list">
                        <div><dt>Name</dt><dd>{{ $selected->name ?: 'Not provided' }}</dd></div>
                        <div><dt>Email</dt><dd><a class="admin-break-text" href="mailto:{{ $selected->email }}">{{ $selected->email }}</a></dd></div>
                        <div><dt>Phone</dt><dd>{{ $selected->phone ?: 'Not provided' }}</dd></div>
                        <div><dt>Subject</dt><dd>{{ $selected->subject ?: 'Not provided' }}</dd></div>
                        @foreach ($selected->payload as $label => $value)
                            <div>
                                <dt>{{ \Illuminate\Support\Str::headline($label) }}</dt>
                                <dd class="admin-pre-wrap">
                                    @if (is_bool($value))
                                        {{ $value ? 'Yes' : 'No' }}
                                    @else
                                        {{ is_scalar($value) ? $value : json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </aside>
        @endif
    </div>
</div>
