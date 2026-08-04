@props(['url'])

@php
    $path = trim(strtolower((string) (parse_url($url, PHP_URL_PATH) ?: '/')), '/');
    $section = explode('/', $path)[0] ?: 'home';
    $fragment = strtolower((string) (parse_url($url, PHP_URL_FRAGMENT) ?: ''));

    if ($section === 'home' && $fragment === 'footer') {
        $section = 'policies';
    }

    $icon = match ($section) {
        'home' => 'nav-home.svg',
        'about' => 'nav-about.svg',
        'roster', 'team', 'players' => 'users.svg',
        'schedule', 'games' => 'calendar.svg',
        'sponsors', 'partners' => 'handshake.svg',
        'gallery' => 'nav-gallery.svg',
        'contact' => 'mail.svg',
        'policies', 'policy', 'privacy', 'terms' => 'nav-policies.svg',
        default => 'nav-link.svg',
    };
@endphp

<img {{ $attributes->class('nav-link-icon') }} src="{{ asset('assets/icons/'.$icon) }}" alt="" width="18" height="18">
