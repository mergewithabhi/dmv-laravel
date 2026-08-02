<!doctype html>
<html lang="en">
<head>
    @php
        $page = $page ?? null;
        $meta = $meta ?? [];
        $description = $meta['description'] ?? $description ?? $page?->seo_description ?? 'DMV Warriors basketball.';
        $metaTitle = $meta['title'] ?? $page?->seo_title ?? (($page?->title ?? 'DMV Warriors').' | DMV Warriors');
        $indexable = $meta['indexable'] ?? $page?->is_indexable ?? true;
        $canonical = $meta['canonical'] ?? $page?->canonical_url ?? url()->current();
        $ogImage = $meta['og_image'] ?? $page?->ogMedia?->url('web') ?? asset('assets/main_hero_banner.png');
        $pageKey = $meta['page_key'] ?? $page?->template_key ?? 'content';
        $calendarData = $calendarData ?? [];
        $structuredData = $structuredData ?? [];
    @endphp
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $metaTitle }}</title>
    <meta name="description" content="{{ $description }}">
    <meta name="robots" content="{{ $indexable ? 'index,follow' : 'noindex,nofollow' }}">
    <link rel="canonical" href="{{ $canonical }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800;900&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body
    data-page="{{ $pageKey }}"
    data-app-url="{{ url('/') }}"
    data-navigation-key="{{ $pageKey }}-{{ $page?->updated_at?->timestamp ?? now()->timestamp }}"
>
    <a class="skip-link" href="#site-main">Skip to main content</a>
    @include('components.site.header')
    {{ $slot }}
    @include('components.site.footer')
    @include('components.confirm-dialog')
    <script>
        window.DMVCms = @json($calendarData);
    </script>
    @if (config('services.turnstile.enabled') && config('services.turnstile.site_key'))
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?onload=DMVTurnstileLoaded&render=explicit" defer></script>
    @endif
    @livewireScripts
</body>
</html>
