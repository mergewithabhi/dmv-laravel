@foreach ($galleryImages as $item)
    @php
        $asset = $item->mediaAsset;
        $imageUrl = $asset->url('web') ?: $asset->url();
        $alt = $item->alt_text ?: $asset->alt_text ?: $item->title;
    @endphp
    <a
        class="inside-gallery-item"
        href="{{ route('gallery.index', ['type' => 'image', 'item' => $item->id]) }}"
        aria-label="Open in gallery: {{ $alt }}"
        wire:navigate
    >
        <img
            class="family-gallery-image"
            src="{{ $imageUrl }}"
            alt="{{ $alt }}"
            loading="lazy"
            decoding="async"
            style="object-position: {{ $asset->focal_x ?? 50 }}% {{ $asset->focal_y ?? 50 }}%"
        >
    </a>
@endforeach
