<main id="site-main" class="dynamic-page gallery-page">
    <header class="content-page-hero gallery-hero">
        <img
            class="gallery-hero-image"
            src="{{ asset('assets/gallery-hero-banner.webp') }}"
            alt=""
            aria-hidden="true"
            width="1600"
            height="854"
            decoding="async"
            fetchpriority="high"
        >
        <div class="container">
            <span>DMV Warriors</span>
            <h1>Gallery</h1>
            <p>Game day, team moments, and community events.</p>
        </div>
    </header>
    <section class="container gallery-content" aria-label="Photo gallery" data-gallery-root>
        <div class="public-gallery-toolbar" aria-label="Gallery filters">
            <div class="public-gallery-filter">
                <label for="public-gallery-sort">Sort by</label>
                <select id="public-gallery-sort" wire:model.live="sort">
                    <option value="recent">Most recent</option>
                    <option value="oldest">Oldest first</option>
                </select>
            </div>
            <div class="public-gallery-filter">
                <label for="public-gallery-type">Type</label>
                <select id="public-gallery-type" wire:model.live="type">
                    <option value="">All media</option>
                    <option value="image">Images</option>
                    <option value="video">Videos</option>
                </select>
            </div>
            <span class="public-gallery-updating" wire:loading wire:target="sort,type">Updating...</span>
        </div>
        <div class="public-gallery-grid" wire:loading.class="is-updating" wire:target="sort,type">
            @forelse ($items as $item)
                @php($isVideo = $item->mediaAsset->kind->value === 'video')
                <figure class="public-gallery-item">
                    <button
                        class="public-gallery-trigger"
                        type="button"
                        aria-label="Open {{ $isVideo ? 'video' : 'full-size image' }}: {{ $item->title }}"
                        data-gallery-open
                        data-gallery-id="{{ $item->id }}"
                        data-gallery-type="{{ $isVideo ? 'video' : 'image' }}"
                        data-gallery-src="{{ $item->mediaAsset->url() }}"
                        data-gallery-alt="{{ $item->alt_text ?: $item->mediaAsset->alt_text }}"
                        data-gallery-title="{{ $item->title }}"
                        data-gallery-caption="{{ $item->caption }}"
                    >
                        @if ($isVideo)
                            <video
                                src="{{ $item->mediaAsset->url() }}"
                                aria-label="{{ $item->alt_text ?: $item->mediaAsset->alt_text }}"
                                muted
                                playsinline
                                preload="metadata"
                            ></video>
                            <span class="public-gallery-video-badge" aria-hidden="true"></span>
                        @else
                            <img
                                src="{{ $item->mediaAsset->url('thumb') ?: $item->mediaAsset->url() }}"
                                alt="{{ $item->alt_text ?: $item->mediaAsset->alt_text }}"
                                loading="lazy"
                                decoding="async"
                                style="object-position: {{ $item->mediaAsset->focal_x ?? 50 }}% {{ $item->mediaAsset->focal_y ?? 50 }}%"
                            >
                        @endif
                    </button>
                    @if ($item->title || $item->caption)
                        <figcaption>
                            @if ($item->title)<strong>{{ $item->title }}</strong>@endif
                            @if ($item->caption)<span>{{ $item->caption }}</span>@endif
                        </figcaption>
                    @endif
                </figure>
            @empty
                <p class="gallery-empty" role="status">No gallery items match these filters.</p>
            @endforelse
        </div>
        @include('livewire.site.partials.gallery-pagination', ['paginator' => $items])

        @if ($items->isNotEmpty())
            <dialog class="gallery-lightbox" data-gallery-lightbox aria-label="Gallery media viewer">
                <div class="gallery-lightbox-frame">
                    <button class="gallery-lightbox-close" type="button" data-gallery-lightbox-close aria-label="Close media viewer">&times;</button>
                    <button class="gallery-lightbox-nav previous" type="button" data-gallery-lightbox-previous aria-label="Previous gallery item">
                        <img src="{{ asset('assets/icons/arrow-right.svg') }}" alt="">
                    </button>
                    <figure>
                        <div class="gallery-lightbox-media">
                            <img data-gallery-lightbox-image alt="">
                            <video data-gallery-lightbox-video controls playsinline preload="metadata" hidden></video>
                        </div>
                        <figcaption>
                            <strong data-gallery-lightbox-title></strong>
                            <span data-gallery-lightbox-caption></span>
                        </figcaption>
                    </figure>
                    <button class="gallery-lightbox-nav next" type="button" data-gallery-lightbox-next aria-label="Next gallery item">
                        <img src="{{ asset('assets/icons/arrow-right.svg') }}" alt="">
                    </button>
                </div>
            </dialog>
        @endif
    </section>
</main>
