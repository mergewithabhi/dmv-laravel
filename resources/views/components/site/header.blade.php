@php
    use App\Livewire\Site\Support\PublicInteractionNormalizer;

    $logoId = $settings['branding.logo_media_id'] ?? null;
    $logoAsset = $logoId ? $settingMedia->get((int) $logoId) : null;
    $logo = $logoAsset?->url('thumb') ?: $logoAsset?->url();
    $homeUrl = PublicInteractionNormalizer::url('/#home');
    $ticketUrl = PublicInteractionNormalizer::url($settings['tickets.global_url'] ?? '/schedule');
@endphp
<header class="site-header" data-reveal="down">
    <div class="container header-inner">
        <a class="brand" href="{{ $homeUrl }}" wire:navigate aria-label="{{ $settings['branding.site_name'] ?? 'DMV Warriors' }} home">
            <img
                src="{{ $logo ?: asset('assets/bmv_logo_transparent.png') }}"
                alt="{{ $settings['branding.site_name'] ?? 'DMV Warriors' }}"
                width="1024"
                height="1024"
                decoding="async"
                fetchpriority="high"
            >
        </a>

        <button
            class="nav-toggle icon-button"
            type="button"
            aria-label="Open navigation"
            aria-controls="primary-navigation"
            aria-expanded="false"
            aria-haspopup="true"
        >
            <span></span><span></span><span></span>
        </button>

        <nav id="primary-navigation" class="primary-nav" aria-label="Primary navigation">
            @foreach ($navigation as $item)
                @php($itemUrl = PublicInteractionNormalizer::url($item->url))
                <a
                    data-page-link="{{ trim(parse_url($item->url, PHP_URL_PATH) ?: '/', '/') ?: 'home' }}"
                    href="{{ $itemUrl }}"
                    @if(PublicInteractionNormalizer::isInternal($itemUrl) && $item->target !== '_blank') wire:navigate @endif
                    @if($item->target === '_blank') target="_blank" rel="noopener noreferrer" @endif
                >{{ $item->label }}</a>
            @endforeach
        </nav>

        <a
            class="button button-primary header-ticket"
            href="{{ $ticketUrl }}"
            @if(PublicInteractionNormalizer::isInternal($ticketUrl)) wire:navigate @else target="_blank" rel="noopener noreferrer" @endif
        >
            <img src="{{ asset('assets/icons/ticket.svg') }}" alt="" width="24" height="24">
            {{ $settings['tickets.button_label'] ?? 'Buy Tickets' }}
        </a>
    </div>
</header>
