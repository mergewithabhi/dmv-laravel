@php
    $selectedId = filled($selectedId ?? null) ? (int) $selectedId : null;
    $selectedAsset = $selectedId ? $assets->firstWhere('id', $selectedId) : null;
    $inputKinds = $inputKinds ?? [$inputKind];
    $allowedAssets = $assets->filter(fn ($asset) => in_array($asset->kind->value, $inputKinds, true));
    $selectedMedia = $selectedAsset?->getFirstMedia('file');
    $dialogId = $inputId.'-library';
    $mediaLabel = $mediaLabel ?? ($inputKind === 'icon' ? 'icon' : 'image');
@endphp

<div class="admin-media-field">
    <div class="admin-media-current">
        <div class="admin-media-preview">
            @if ($selectedAsset && str_starts_with($selectedMedia?->mime_type ?? '', 'image/'))
                <img src="{{ $selectedAsset->url('thumb') ?: $selectedAsset->url() }}" alt="">
            @elseif ($selectedAsset && str_starts_with($selectedMedia?->mime_type ?? '', 'video/'))
                <video src="{{ $selectedAsset->url() }}" muted playsinline preload="metadata"></video>
            @else
                <span>No {{ $mediaLabel }}</span>
            @endif
        </div>
        <div class="admin-media-current-meta">
            <strong>{{ $selectedAsset?->title ?? 'Nothing selected' }}</strong>
            <div class="admin-actions">
                <label class="admin-button secondary small admin-file-button" for="{{ $inputId }}-upload">Upload</label>
                <button class="admin-button secondary small" type="button" data-media-dialog-open="{{ $dialogId }}">Library</button>
                @if ($selectedAsset)
                    <button class="admin-icon-button" type="button" wire:click="{{ $clearAction }}" title="Remove selected media" aria-label="Remove selected media">&times;</button>
                @endif
            </div>
        </div>
    </div>

    <div class="admin-media-upload">
        <input id="{{ $inputId }}-upload" class="admin-visually-hidden-file" type="file" wire:model="mediaUploads.{{ $uploadKey }}" accept="{{ $acceptedTypes }}">
        @if (data_get($this, "mediaUploads.{$uploadKey}"))
            <span>File ready to upload</span>
            <button class="admin-button small" type="button" wire:click="{{ $uploadAction }}" wire:loading.attr="disabled" wire:target="{{ $uploadAction }}">Use this file</button>
        @endif
        <span class="admin-upload-status" wire:loading wire:target="mediaUploads.{{ $uploadKey }},{{ $uploadAction }}">Processing...</span>
    </div>
    @error("mediaUploads.{$uploadKey}")<span class="admin-field-error">{{ $message }}</span>@enderror

    <dialog id="{{ $dialogId }}" class="admin-media-dialog" data-media-dialog>
        <div class="admin-media-dialog-header">
            <div><strong>Choose from media library</strong><small>{{ $allowedAssets->count() }} available</small></div>
            <button class="admin-icon-button" type="button" data-media-dialog-close aria-label="Close media library">&times;</button>
        </div>
        <div class="admin-media-dialog-search">
            <input type="search" placeholder="Search media" data-media-search>
        </div>
        <div class="admin-media-library-grid">
            @forelse ($allowedAssets as $asset)
                @php $assetMedia = $asset->getFirstMedia('file'); @endphp
                <button class="admin-media-choice {{ $selectedId === $asset->id ? 'selected' : '' }}" type="button" wire:click="{{ $selectAction($asset->id) }}" data-media-choice data-media-title="{{ strtolower($asset->title) }}">
                    @if (str_starts_with($assetMedia?->mime_type ?? '', 'image/'))
                        <img src="{{ $asset->url('thumb') ?: $asset->url() }}" alt="">
                    @elseif (str_starts_with($assetMedia?->mime_type ?? '', 'video/'))
                        <video src="{{ $asset->url() }}" muted playsinline preload="metadata"></video>
                    @else
                        <span>{{ strtoupper($asset->kind->value) }}</span>
                    @endif
                    <strong>{{ $asset->title }}</strong>
                </button>
            @empty
                <p class="admin-empty">No matching media has been uploaded.</p>
            @endforelse
        </div>
    </dialog>
</div>
