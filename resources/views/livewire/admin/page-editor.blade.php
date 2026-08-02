<div>
    <div class="admin-page-heading">
        <div><h2>{{ $page->title }}</h2><p>Template: {{ $page->template_key }}. Layout and section order are locked.</p></div>
        <div class="admin-actions">
            <a class="admin-button secondary" href="{{ $previewUrl }}" target="_blank">Preview</a>
            @if ($page->workflow_status->value === 'draft')
                <button class="admin-button secondary" type="button" wire:click="submit" wire:loading.attr="disabled">Submit for review</button>
            @endif
            @can('publish content')
                @if ($page->workflow_status->value === 'in_review')
                    <input class="admin-schedule-input" type="datetime-local" wire:model="publishAt" aria-label="Optional scheduled publication time">
                    <button class="admin-button" type="button" wire:click="publish" wire:loading.attr="disabled">{{ $publishAt ? 'Schedule approval' : 'Approve and publish' }}</button>
                @endif
            @endcan
        </div>
    </div>
    @error('publishAt')<div class="admin-alert error">{{ $message }}</div>@enderror

    <form wire:submit="save">
        @if ($this->canEditPageSettings())
            <section class="admin-panel section-editor">
                <div class="admin-panel-header"><h3>SEO and page settings</h3><span class="status-badge {{ $page->workflow_status->value }}">{{ $page->workflow_status->label() }}</span></div>
                <div class="admin-panel-body admin-form-grid">
                    <div class="admin-field"><label>Page title</label><input wire:model="pageForm.title">@error('pageForm.title')<span class="admin-field-error">{{ $message }}</span>@enderror</div>
                    <div class="admin-field"><label>SEO title</label><input wire:model="pageForm.seo_title">@error('pageForm.seo_title')<span class="admin-field-error">{{ $message }}</span>@enderror</div>
                    <div class="admin-field full"><label>SEO description</label><textarea wire:model="pageForm.seo_description"></textarea>@error('pageForm.seo_description')<span class="admin-field-error">{{ $message }}</span>@enderror</div>
                    <div class="admin-field"><label>Canonical URL</label><input type="url" wire:model="pageForm.canonical_url">@error('pageForm.canonical_url')<span class="admin-field-error">{{ $message }}</span>@enderror</div>
                    <div class="admin-field"><label>Social image</label><select wire:model="pageForm.og_media_id"><option value="">Default image</option>@foreach($media->whereIn('kind.value', ['image', 'icon']) as $asset)<option value="{{ $asset->id }}">{{ $asset->title }}</option>@endforeach</select>@error('pageForm.og_media_id')<span class="admin-field-error">{{ $message }}</span>@enderror</div>
                    <div class="admin-field full"><label><input type="checkbox" wire:model="pageForm.is_indexable"> Allow search-engine indexing</label>@error('pageForm.is_indexable')<span class="admin-field-error">{{ $message }}</span>@enderror</div>
                    @error('pageForm')<div class="admin-field full admin-field-error">{{ $message }}</div>@enderror
                </div>
            </section>
        @endif

        @foreach ($page->sections as $section)
            @php $editableFields = collect($section->field_schema['fields'] ?? [])->filter(fn ($field, $fieldId) => $this->canEditField($section->id, $fieldId, $field)); @endphp
            @continue($editableFields->isEmpty() && ! $this->canEditSection($section->id))
            <details class="admin-panel section-editor" @if($loop->first) open @endif>
                <summary class="admin-panel-header">
                    <h3>{{ $section->label }} <small>({{ $editableFields->count() }} of {{ count($section->field_schema['fields'] ?? []) }} fields editable)</small></h3>
                    @if ($this->canEditSection($section->id))
                        <label><input type="checkbox" wire:model="sections.{{ $section->id }}.is_enabled"> Enabled</label>
                        @error("sections.{$section->id}.is_enabled")<span class="admin-field-error">{{ $message }}</span>@enderror
                    @endif
                </summary>
                <div class="admin-panel-body admin-form-grid">
                    @foreach ($editableFields as $fieldId => $field)
                        <div class="admin-field {{ ($field['input'] ?? '') === 'textarea' ? 'full' : '' }}">
                            <label for="section-{{ $section->id }}-{{ $fieldId }}">{{ $field['label'] }}</label>
                            @if (in_array($field['input'] ?? '', ['media', 'icon'], true))
                                <select id="section-{{ $section->id }}-{{ $fieldId }}" wire:model="sections.{{ $section->id }}.payload.{{ $fieldId }}">
                                    <option value="">No media</option>
                                    @foreach ($media->where('kind.value', ($field['input'] ?? '') === 'icon' ? 'icon' : 'image') as $asset)
                                        <option value="{{ $asset->id }}">{{ $asset->title }}</option>
                                    @endforeach
                                </select>
                            @elseif (($field['input'] ?? '') === 'textarea')
                                <textarea id="section-{{ $section->id }}-{{ $fieldId }}" wire:model="sections.{{ $section->id }}.payload.{{ $fieldId }}"></textarea>
                            @else
                                <input id="section-{{ $section->id }}-{{ $fieldId }}" type="{{ ($field['input'] ?? '') === 'datetime' ? 'datetime-local' : 'text' }}" wire:model="sections.{{ $section->id }}.payload.{{ $fieldId }}">
                            @endif
                            @error("sections.{$section->id}.payload.{$fieldId}")<span class="admin-field-error">{{ $message }}</span>@enderror
                        </div>
                    @endforeach
                    @if ($editableFields->isEmpty())
                        <p class="admin-empty">You do not have permission to edit any fields in this section.</p>
                    @endif
                </div>
            </details>
        @endforeach
        <div class="admin-actions">
            <button class="admin-button" type="submit" wire:loading.attr="disabled">Save draft</button>
            <a class="admin-button secondary" href="{{ route('admin.pages') }}" wire:navigate>Back to pages</a>
        </div>
    </form>

    @can('publish content')
        <details class="admin-panel revision-panel">
            <summary class="admin-panel-header"><h3>Revision history</h3><span>{{ $revisions->count() }} recent revisions</span></summary>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead><tr><th>Version</th><th>Area</th><th>Event</th><th>Author</th><th>Saved</th><th>Action</th></tr></thead>
                    <tbody>
                    @forelse ($revisions as $revision)
                        <tr>
                            <td>#{{ $revision->version }}</td>
                            <td>{{ $revision->revisionable_type === $page->getMorphClass() ? 'Page settings' : ($sectionLabels[$revision->revisionable_id] ?? 'Page section') }}</td>
                            <td>{{ \Illuminate\Support\Str::headline($revision->event) }}</td>
                            <td>{{ $revision->user?->name ?? 'System' }}</td>
                            <td>{{ $revision->created_at->format('M j, Y g:i A') }}</td>
                            <td>
                                <button
                                    class="admin-button secondary small"
                                    type="button"
                                    wire:click="restoreRevision({{ $revision->id }})"
                                    data-confirm-title="Restore revision?"
                                    data-confirm-message="Current values will remain available in revision history."
                                    data-confirm-button="Restore revision"
                                    data-confirm-variant="warning"
                                >Restore</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="admin-empty">No revisions have been recorded.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </details>
    @endcan
</div>
