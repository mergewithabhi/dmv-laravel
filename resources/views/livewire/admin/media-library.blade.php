<div>
    <div class="admin-page-heading">
        <div><h2>Media library</h2><p>Managed images, logos, documents, and sanitized SVG icons.</p></div>
    </div>
    <section class="admin-panel section-editor">
        <div class="admin-panel-header"><h3>Upload asset</h3></div>
        <form class="admin-panel-body admin-form-grid" wire:submit="uploadAsset">
            <div class="admin-field">
                <label for="media-upload">File</label>
                <input id="media-upload" type="file" wire:model="upload" accept=".jpg,.jpeg,.png,.webp,.gif,.svg,.mp4,.webm,.pdf,.ics" @error('upload') aria-invalid="true" aria-describedby="media-upload-error" @enderror>
                @error('upload')<span id="media-upload-error" class="admin-field-error">{{ $message }}</span>@enderror
            </div>
            <div class="admin-field">
                <label for="media-kind">Asset type</label>
                <select id="media-kind" wire:model="kind" @error('kind') aria-invalid="true" aria-describedby="media-kind-error" @enderror>
                    <option value="image">Image</option>
                    <option value="icon">Icon</option>
                    <option value="video">Video</option>
                    <option value="document">Document</option>
                </select>
                @error('kind')<span id="media-kind-error" class="admin-field-error">{{ $message }}</span>@enderror
            </div>
            <div class="admin-field">
                <label for="media-title">Title</label>
                <input id="media-title" wire:model="title" @error('title') aria-invalid="true" aria-describedby="media-title-error" @enderror>
                @error('title')<span id="media-title-error" class="admin-field-error">{{ $message }}</span>@enderror
            </div>
            <div class="admin-field">
                <label for="media-alt-text">Alternative text</label>
                <input id="media-alt-text" wire:model="altText" @error('altText') aria-invalid="true" aria-describedby="media-alt-text-error" @enderror>
                @error('altText')<span id="media-alt-text-error" class="admin-field-error">{{ $message }}</span>@enderror
            </div>
            <div class="admin-field full">
                <label class="admin-checkbox" for="media-decorative">
                    <input id="media-decorative" type="checkbox" wire:model.live="isDecorative">
                    Decorative asset with no alternative text
                </label>
            </div>
            <div class="admin-actions"><button class="admin-button" type="submit">Upload</button><span wire:loading wire:target="upload,uploadAsset">Processing...</span></div>
        </form>
    </section>

    @if ($editingId)
        <section
            id="media-metadata-editor"
            class="admin-panel section-editor"
            tabindex="-1"
            aria-labelledby="media-metadata-editor-heading"
            data-admin-action-area
            wire:key="media-editor-{{ $editingId }}"
        >
            <div class="admin-panel-header"><h3 id="media-metadata-editor-heading">Edit metadata</h3><button class="admin-button secondary small" type="button" wire:click="cancelEdit">Close</button></div>
            <form class="admin-panel-body admin-form-grid" wire:submit="saveMetadata">
                <div class="admin-field">
                    <label for="metadata-title">Title</label>
                    <input id="metadata-title" wire:model="metadata.title" @error('metadata.title') aria-invalid="true" aria-describedby="metadata-title-error" @enderror>
                    @error('metadata.title')<span id="metadata-title-error" class="admin-field-error">{{ $message }}</span>@enderror
                </div>
                <div class="admin-field">
                    <label for="metadata-alt-text">Alternative text</label>
                    <input id="metadata-alt-text" wire:model="metadata.alt_text" @error('metadata.alt_text') aria-invalid="true" aria-describedby="metadata-alt-text-error" @enderror>
                    @error('metadata.alt_text')<span id="metadata-alt-text-error" class="admin-field-error">{{ $message }}</span>@enderror
                </div>
                <div class="admin-field full">
                    <label for="metadata-caption">Caption</label>
                    <textarea id="metadata-caption" wire:model="metadata.caption" @error('metadata.caption') aria-invalid="true" aria-describedby="metadata-caption-error" @enderror></textarea>
                    @error('metadata.caption')<span id="metadata-caption-error" class="admin-field-error">{{ $message }}</span>@enderror
                </div>
                <div class="admin-field">
                    <label for="metadata-credit">Credit</label>
                    <input id="metadata-credit" wire:model="metadata.credit" @error('metadata.credit') aria-invalid="true" aria-describedby="metadata-credit-error" @enderror>
                    @error('metadata.credit')<span id="metadata-credit-error" class="admin-field-error">{{ $message }}</span>@enderror
                </div>
                <div class="admin-field">
                    <label for="metadata-kind">Type</label>
                    <select id="metadata-kind" wire:model="metadata.kind" @error('metadata.kind') aria-invalid="true" aria-describedby="metadata-kind-error" @enderror>
                        <option value="image">Image</option>
                        <option value="icon">Icon</option>
                        <option value="video">Video</option>
                        <option value="document">Document</option>
                    </select>
                    @error('metadata.kind')<span id="metadata-kind-error" class="admin-field-error">{{ $message }}</span>@enderror
                </div>
                <div class="admin-field">
                    <label for="metadata-focal-x">Horizontal focal point</label>
                    <input id="metadata-focal-x" type="number" min="0" max="100" wire:model="metadata.focal_x" @error('metadata.focal_x') aria-invalid="true" aria-describedby="metadata-focal-x-error" @enderror>
                    @error('metadata.focal_x')<span id="metadata-focal-x-error" class="admin-field-error">{{ $message }}</span>@enderror
                </div>
                <div class="admin-field">
                    <label for="metadata-focal-y">Vertical focal point</label>
                    <input id="metadata-focal-y" type="number" min="0" max="100" wire:model="metadata.focal_y" @error('metadata.focal_y') aria-invalid="true" aria-describedby="metadata-focal-y-error" @enderror>
                    @error('metadata.focal_y')<span id="metadata-focal-y-error" class="admin-field-error">{{ $message }}</span>@enderror
                </div>
                <div class="admin-field full">
                    <label class="admin-checkbox" for="metadata-decorative">
                        <input id="metadata-decorative" type="checkbox" wire:model="metadata.is_decorative">
                        Decorative asset
                    </label>
                    @error('metadata.is_decorative')<span class="admin-field-error">{{ $message }}</span>@enderror
                </div>
                <button class="admin-button" type="submit">Save metadata</button>
            </form>
        </section>
    @endif

    <section class="admin-panel">
        <div class="admin-panel-header"><h3>Assets</h3></div>
        <div class="admin-panel-body">
            <div class="admin-toolbar" aria-label="Media filters">
                <div class="admin-filter admin-filter-grow">
                    <label for="media-search">Search assets</label>
                    <input id="media-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Search by title">
                </div>
                <div class="admin-filter">
                    <label for="media-kind-filter">Asset type</label>
                    <select id="media-kind-filter" wire:model.live="kindFilter">
                        <option value="">All types</option>
                        <option value="image">Images</option>
                        <option value="icon">Icons</option>
                        <option value="video">Videos</option>
                        <option value="document">Documents</option>
                    </select>
                </div>
            </div>
            <div class="media-picker-grid">
                @forelse ($assets as $asset)
                    <article class="media-picker-item" wire:key="asset-{{ $asset->id }}">
                        @if (str_starts_with($asset->getFirstMedia('file')?->mime_type ?? '', 'image/'))
                            <img src="{{ $asset->url('thumb') ?: $asset->url() }}" alt="{{ $asset->is_decorative ? '' : $asset->alt_text }}">
                        @else
                            <div class="admin-empty">{{ strtoupper($asset->kind->value) }}</div>
                        @endif
                        <strong>{{ $asset->title }}</strong>
                        <small>{{ $asset->kind->value }}</small>
                        <div class="admin-actions">
                            <button
                                class="admin-button secondary small"
                                type="button"
                                wire:click="edit({{ $asset->id }})"
                                data-admin-focus-target="#media-metadata-editor"
                            >Edit</button>
                            <button
                                class="admin-button danger small"
                                type="button"
                                wire:click="delete({{ $asset->id }})"
                                data-confirm-title="Delete asset?"
                                data-confirm-message="Delete this unused asset? This cannot be undone."
                                data-confirm-button="Delete asset"
                            >Delete</button>
                        </div>
                    </article>
                @empty
                    <p class="admin-empty">No assets match the current filters.</p>
                @endforelse
            </div>
        </div>
        @include('livewire.admin.partials.pagination', ['paginator' => $assets])
    </section>
</div>
