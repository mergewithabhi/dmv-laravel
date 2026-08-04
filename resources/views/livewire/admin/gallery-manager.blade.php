<div>
    @php
        $itemIds = $items->pluck('id')->map(fn ($id) => (int) $id);
        $selectedIds = collect($selected)->map(fn ($id) => (int) $id);
        $allPageSelected = $itemIds->isNotEmpty() && $itemIds->every(fn ($id) => $selectedIds->contains($id));
    @endphp

    <div class="admin-page-heading">
        <div>
            <h2>Website gallery</h2>
            <p>Choose the images and videos visitors can see on the public Gallery page.</p>
        </div>
        <div class="admin-actions">
            <a class="admin-button secondary" href="{{ route('gallery.index') }}" target="_blank" rel="noopener">View gallery</a>
            <button class="admin-button secondary" type="button" wire:click="create" data-admin-focus-target="#gallery-editor">Choose from library</button>
        </div>
    </div>

    <section class="admin-panel gallery-quick-upload">
        <div class="admin-panel-header"><h3>Upload gallery media</h3></div>
        <form class="admin-panel-body" wire:submit="uploadImages">
            <label class="gallery-upload-picker" for="gallery-batch-upload">
                <input
                    id="gallery-batch-upload"
                    type="file"
                    wire:model="uploads"
                    accept=".jpg,.jpeg,.png,.webp,.gif,.mp4,.mov,.webm"
                    multiple
                    data-gallery-upload-input
                >
                <img src="{{ asset('assets/icons/download.svg') }}" alt="">
                <strong>Choose images or videos</strong>
                <span>JPG, PNG, WebP, GIF, MP4, MOV or WebM, up to 20 files and 100 MB each</span>
            </label>
            <div
                class="gallery-upload-progress"
                data-gallery-upload-progress
                role="progressbar"
                aria-label="Upload progress"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-valuenow="0"
                hidden
            >
                <progress data-gallery-upload-bar max="100" value="0">0%</progress>
                <strong data-gallery-upload-label>Uploading 0%</strong>
            </div>
            @if ($uploads)
                <div class="gallery-upload-selection">
                    <div class="gallery-upload-summary">
                        <strong>{{ count($uploads) }} {{ str('file')->plural(count($uploads)) }} selected</strong>
                        <button class="admin-button secondary small" type="button" wire:click="$set('uploads', [])">Clear</button>
                    </div>
                    <div class="gallery-upload-preview">
                        @foreach ($uploads as $index => $upload)
                            <figure wire:key="gallery-upload-{{ $index }}">
                                @if (str_starts_with((string) $upload->getMimeType(), 'image/'))
                                    <img src="{{ $upload->temporaryUrl() }}" alt="">
                                @elseif (str_starts_with((string) $upload->getMimeType(), 'video/'))
                                    <video src="{{ $upload->temporaryUrl() }}" muted playsinline preload="metadata"></video>
                                @endif
                                <figcaption>{{ \Illuminate\Support\Str::limit($upload->getClientOriginalName(), 28) }}</figcaption>
                            </figure>
                        @endforeach
                    </div>
                    <button class="admin-button" type="submit" wire:loading.attr="disabled" wire:target="uploadImages">
                        <span wire:loading.remove wire:target="uploadImages">Add to gallery</span>
                        <span wire:loading wire:target="uploadImages">Uploading...</span>
                    </button>
                </div>
            @endif
            @error('uploads')<span class="admin-field-error">{{ $message }}</span>@enderror
            @error('uploads.*')<span class="admin-field-error">{{ $message }}</span>@enderror
        </form>
    </section>

    @if ($showEditor)
        <section
            id="gallery-editor"
            class="admin-panel section-editor"
            tabindex="-1"
            aria-labelledby="gallery-editor-heading"
            data-admin-action-area
        >
            <div class="admin-panel-header">
                <h3 id="gallery-editor-heading">{{ $editingId ? 'Edit gallery item' : 'Choose from media library' }}</h3>
                <button class="admin-button secondary small" type="button" wire:click="cancelEditor">Close</button>
            </div>
            <form class="admin-panel-body admin-form-grid" wire:submit="save">
                <div class="admin-field full">
                    <label>Image or video</label>
                    @include('livewire.admin.partials.media-field', [
                        'assets' => $media,
                        'selectedId' => $form['media_asset_id'],
                        'inputKind' => 'image',
                        'inputKinds' => ['image', 'video'],
                        'mediaLabel' => 'image or video',
                        'inputId' => 'gallery-image',
                        'uploadKey' => 'editor',
                        'acceptedTypes' => '.jpg,.jpeg,.png,.webp,.gif,.mp4,.mov,.webm',
                        'uploadAction' => 'uploadMedia',
                        'clearAction' => 'clearMedia',
                        'selectAction' => fn ($assetId) => "selectMedia({$assetId})",
                    ])
                    @error('form.media_asset_id')<span class="admin-field-error">{{ $message }}</span>@enderror
                </div>
                <div class="admin-field">
                    <label for="gallery-title">Title</label>
                    <input id="gallery-title" wire:model="form.title" @error('form.title') aria-invalid="true" @enderror>
                    @error('form.title')<span class="admin-field-error">{{ $message }}</span>@enderror
                </div>
                <div class="admin-field">
                    <label for="gallery-alt-text">Media description</label>
                    <input id="gallery-alt-text" wire:model="form.alt_text" @error('form.alt_text') aria-invalid="true" @enderror>
                    <small class="admin-field-help">Briefly describe what is visible for visitors using screen readers.</small>
                    @error('form.alt_text')<span class="admin-field-error">{{ $message }}</span>@enderror
                </div>
                <div class="admin-field full">
                    <label for="gallery-caption">Caption</label>
                    <textarea id="gallery-caption" wire:model="form.caption" @error('form.caption') aria-invalid="true" @enderror></textarea>
                    @error('form.caption')<span class="admin-field-error">{{ $message }}</span>@enderror
                </div>
                <div class="admin-field">
                    <label for="gallery-position">Display order</label>
                    <input id="gallery-position" type="number" min="0" max="65535" step="1" wire:model="form.position">
                    <small class="admin-field-help">Lower numbers appear first.</small>
                    @error('form.position')<span class="admin-field-error">{{ $message }}</span>@enderror
                </div>
                <div class="admin-field">
                    <label class="admin-switch" for="gallery-published">
                        <input id="gallery-published" type="checkbox" wire:model="form.is_published">
                        <span aria-hidden="true"></span>
                        Show on website
                    </label>
                    @error('form.is_published')<span class="admin-field-error">{{ $message }}</span>@enderror
                </div>
                <div class="admin-actions">
                    <button class="admin-button" type="submit" wire:loading.attr="disabled" wire:target="save">Save image</button>
                    <button class="admin-button secondary" type="button" wire:click="cancelEditor">Cancel</button>
                </div>
            </form>
        </section>
    @endif

    <section class="admin-panel">
        <div class="admin-panel-body">
            <div class="admin-toolbar" aria-label="Gallery filters">
                <div class="admin-filter admin-filter-grow">
                    <label for="gallery-search">Search gallery</label>
                    <input id="gallery-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Title or caption">
                </div>
                <div class="admin-filter">
                    <label for="gallery-type">Type</label>
                    <select id="gallery-type" wire:model.live="typeFilter">
                        <option value="">All media</option>
                        <option value="image">Images</option>
                        <option value="video">Videos</option>
                    </select>
                </div>
                <div class="admin-filter admin-filter-compact">
                    <label for="gallery-per-page">Rows</label>
                    <select id="gallery-per-page" wire:model.live="perPage">
                        @foreach ([10, 15, 25, 50] as $size)
                            <option value="{{ $size }}">{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="admin-list-summary">
                <span>{{ number_format($items->total()) }} gallery {{ str('item')->plural($items->total()) }}</span>
                <span wire:loading wire:target="search,typeFilter,perPage">Updating...</span>
            </div>

            @if ($selected)
                <div class="admin-bulk-bar" aria-label="Bulk gallery actions">
                    <strong>{{ count($selected) }} selected</strong>
                    <div class="admin-actions">
                        <button class="admin-button small" type="button" wire:click="bulkPublish">Show on website</button>
                        <button class="admin-button secondary small" type="button" wire:click="bulkUnpublish">Hide from website</button>
                        <button
                            class="admin-button danger small"
                            type="button"
                            wire:click="bulkDelete"
                            data-confirm-title="Remove selected images?"
                            data-confirm-message="The selected items will be removed from the public gallery. Their files will remain in the media library."
                            data-confirm-button="Remove items"
                        >Delete selected</button>
                        <button class="admin-button secondary small" type="button" wire:click="clearSelection">Clear selection</button>
                    </div>
                </div>
            @endif
            @error('selected')<div class="admin-alert error" role="alert">{{ $message }}</div>@enderror
        </div>

        <div class="admin-table-wrap">
            <table class="admin-table admin-table-list gallery-admin-table">
                <caption class="sr-only">Website gallery media</caption>
                <thead>
                    <tr>
                        <th class="admin-select-column">
                            <input
                                type="checkbox"
                                aria-label="Select all gallery items on this page"
                                wire:click="togglePageSelection('{{ $itemIds->implode(',') }}')"
                                @checked($allPageSelected)
                                @disabled($itemIds->isEmpty())
                            >
                        </th>
                        <th>Media</th>
                        <th>Title</th>
                        <th>Order</th>
                        <th>Status</th>
                        <th>Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr wire:key="gallery-item-{{ $item->id }}" class="{{ $selectedIds->contains($item->id) ? 'is-selected' : '' }}">
                            <td class="admin-select-column">
                                <input type="checkbox" value="{{ $item->id }}" wire:model.live="selected" aria-label="Select {{ $item->title }}">
                            </td>
                            <td>
                                @if ($item->mediaAsset->kind->value === 'video')
                                    <div class="gallery-admin-video"><video src="{{ $item->mediaAsset->url() }}" muted playsinline preload="metadata"></video><span>Video</span></div>
                                @else
                                    <img class="gallery-admin-thumb" src="{{ $item->mediaAsset->url('thumb') ?: $item->mediaAsset->url() }}" alt="">
                                @endif
                            </td>
                            <td>
                                <strong>{{ $item->title }}</strong>
                                @if ($item->caption)<small class="admin-table-secondary">{{ \Illuminate\Support\Str::limit($item->caption, 80) }}</small>@endif
                            </td>
                            <td>{{ $item->position }}</td>
                            <td><span class="status-badge {{ $item->is_published ? 'published' : 'archived' }}">{{ $item->is_published ? 'Live' : 'Hidden' }}</span></td>
                            <td>{{ $item->updated_at->format('M j, Y') }}</td>
                            <td>
                                <div class="admin-actions">
                                    <button class="admin-button secondary small" type="button" wire:click="edit({{ $item->id }})" data-admin-focus-target="#gallery-editor">Edit</button>
                                    <button
                                        class="admin-button danger small"
                                        type="button"
                                        wire:click="delete({{ $item->id }})"
                                        data-confirm-title="Remove gallery item?"
                                        data-confirm-message="This item will be removed from the public gallery. Its file will remain in the media library."
                                        data-confirm-button="Remove item"
                                    >Delete</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><p class="admin-empty">No gallery items match the current filters.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('livewire.admin.partials.pagination', ['paginator' => $items])
    </section>
</div>
