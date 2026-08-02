<div class="guided-editor" data-cms-editor data-unsaved="{{ $hasUnsavedChanges ? 'true' : 'false' }}">
    <div class="guided-editor-heading">
        <div>
            <a class="admin-back-link" href="{{ route('admin.pages') }}" wire:navigate>Back to website pages</a>
            <h2>{{ $page->title }}</h2>
            <p>Update the page content, then save your changes.</p>
        </div>
        <div class="admin-actions">
            <span class="guided-save-state" wire:dirty wire:target="pageForm,sections">Unsaved changes</span>
            <a class="admin-button secondary" href="{{ $previewUrl }}" target="_blank" rel="noopener">Preview page</a>
            <button class="admin-button" type="submit" form="page-content-form" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">Save changes</span>
                <span wire:loading wire:target="save">Saving...</span>
            </button>
        </div>
    </div>

    @error('pageForm')<div class="admin-alert error">{{ $message }}</div>@enderror

    <div class="guided-editor-layout">
        <form id="page-content-form" class="guided-editor-form" wire:submit="save">
            @foreach ($page->sections as $section)
                @php
                    $editableFields = $this->editableFieldsFor($section->id);
                    $manageUrl = $this->manageUrl($section->section_key);
                @endphp
                @continue($editableFields->isEmpty() && ! $this->canEditSection($section->id) && ! $manageUrl)
                <details class="editor-section" @if($loop->first) open @endif>
                    <summary>
                        <span>
                            <strong>{{ $section->label }}</strong>
                            <small>{{ $editableFields->count() }} editable {{ str('item')->plural($editableFields->count()) }}</small>
                        </span>
                        <span class="editor-section-chevron" aria-hidden="true"></span>
                    </summary>
                    <div class="editor-section-body">
                        @if ($manageUrl)
                            <div class="managed-content-note">
                                <span>This section also uses shared website content.</span>
                                <a href="{{ $manageUrl }}" wire:navigate>Manage shared content</a>
                            </div>
                        @endif

                        @if ($this->canEditSection($section->id))
                            <label class="admin-switch">
                                <input type="checkbox" wire:model.live="sections.{{ $section->id }}.is_enabled">
                                <span aria-hidden="true"></span>
                                Show this section
                            </label>
                            @error("sections.{$section->id}.is_enabled")<span class="admin-field-error">{{ $message }}</span>@enderror
                        @endif

                        <div class="guided-field-list">
                            @foreach ($editableFields as $fieldId => $field)
                                @php
                                    $isMediaField = ($field['input'] ?? '') === 'media';
                                    $model = "sections.{$section->id}.payload.{$fieldId}";
                                @endphp
                                <div class="admin-field guided-field {{ $isMediaField ? 'media' : '' }}">
                                    <label for="section-{{ $section->id }}-{{ $fieldId }}">{{ $field['editor_label'] }}</label>
                                    @if ($isMediaField)
                                        @php $uploadKey = $this->sectionUploadKey($section->id, $fieldId); @endphp
                                        @include('livewire.admin.partials.media-field', [
                                            'assets' => $media,
                                            'selectedId' => $sections[$section->id]['payload'][$fieldId] ?? null,
                                            'inputKind' => 'image',
                                            'inputId' => "section-{$section->id}-{$fieldId}",
                                            'uploadKey' => $uploadKey,
                                            'acceptedTypes' => $this->acceptedMediaTypes('media'),
                                            'uploadAction' => "uploadSectionMedia({$section->id}, '{$fieldId}')",
                                            'clearAction' => "selectSectionMedia({$section->id}, '{$fieldId}', null)",
                                            'selectAction' => fn ($assetId) => "selectSectionMedia({$section->id}, '{$fieldId}', {$assetId})",
                                        ])
                                    @elseif (($field['input'] ?? '') === 'textarea')
                                        <textarea id="section-{{ $section->id }}-{{ $fieldId }}" wire:model.live.debounce.500ms="{{ $model }}"></textarea>
                                    @else
                                        <input
                                            id="section-{{ $section->id }}-{{ $fieldId }}"
                                            type="{{ ($field['input'] ?? '') === 'datetime' ? 'datetime-local' : 'text' }}"
                                            wire:model.live.debounce.500ms="{{ $model }}"
                                        >
                                    @endif
                                    @if (! empty($field['editor_help']))<small class="admin-field-help">{{ $field['editor_help'] }}</small>@endif
                                    @error($model)<span class="admin-field-error">{{ $message }}</span>@enderror
                                </div>
                            @endforeach
                        </div>
                    </div>
                </details>
            @endforeach

            @if ($this->canEditPageSettings())
                <details class="editor-section advanced-settings">
                    <summary>
                        <span><strong>Advanced page settings</strong><small>Search and social sharing</small></span>
                        <span class="editor-section-chevron" aria-hidden="true"></span>
                    </summary>
                    <div class="editor-section-body">
                        <div class="guided-field-list">
                            <div class="admin-field guided-field">
                                <label for="page-title">Page name</label>
                                <input id="page-title" wire:model.live.debounce.500ms="pageForm.title">
                                @error('pageForm.title')<span class="admin-field-error">{{ $message }}</span>@enderror
                            </div>
                            <div class="admin-field guided-field">
                                <label for="seo-title">Search result title</label>
                                <input id="seo-title" wire:model.live.debounce.500ms="pageForm.seo_title">
                                @error('pageForm.seo_title')<span class="admin-field-error">{{ $message }}</span>@enderror
                            </div>
                            <div class="admin-field guided-field">
                                <label for="seo-description">Search result description</label>
                                <textarea id="seo-description" wire:model.live.debounce.500ms="pageForm.seo_description"></textarea>
                                @error('pageForm.seo_description')<span class="admin-field-error">{{ $message }}</span>@enderror
                            </div>
                            <div class="admin-field guided-field">
                                <label for="canonical-url">Preferred page address</label>
                                <input id="canonical-url" type="url" wire:model.live.debounce.500ms="pageForm.canonical_url">
                                @error('pageForm.canonical_url')<span class="admin-field-error">{{ $message }}</span>@enderror
                            </div>
                            <div class="admin-field guided-field media">
                                <label>Social sharing image</label>
                                @include('livewire.admin.partials.media-field', [
                                    'assets' => $media,
                                    'selectedId' => $pageForm['og_media_id'] ?? null,
                                    'inputKind' => 'image',
                                    'inputId' => 'page-og-media',
                                    'uploadKey' => 'page-og_media_id',
                                    'acceptedTypes' => $this->acceptedMediaTypes('media'),
                                    'uploadAction' => 'uploadPageMedia',
                                    'clearAction' => 'selectPageMedia(null)',
                                    'selectAction' => fn ($assetId) => "selectPageMedia({$assetId})",
                                ])
                            </div>
                            <label class="admin-switch">
                                <input type="checkbox" wire:model.live="pageForm.is_indexable">
                                <span aria-hidden="true"></span>
                                Show this page in search engines
                            </label>
                        </div>
                    </div>
                </details>
            @endif
        </form>

    </div>

    @if ($this->canEditPageSettings())
        <details class="admin-panel revision-panel">
            <summary class="admin-panel-header"><h3>Previous versions</h3><span>{{ $revisions->count() }} saved</span></summary>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead><tr><th>Version</th><th>Changed by</th><th>Saved</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($revisions->where('revisionable_type', $page->getMorphClass()) as $revision)
                        <tr>
                            <td>#{{ $revision->version }}</td>
                            <td>{{ $revision->user?->name ?? 'System' }}</td>
                            <td>{{ $revision->created_at->diffForHumans() }}</td>
                            <td><button class="admin-button secondary small" type="button" wire:click="restoreRevision({{ $revision->id }})" data-confirm-title="Restore this version?" data-confirm-message="This version will immediately replace the live page." data-confirm-button="Restore version" data-confirm-variant="warning">Restore</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="admin-empty">No previous versions yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </details>
    @endif
</div>
