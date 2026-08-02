<main id="site-main" class="dynamic-page">
    @php
        $rawSocialLinks = $person->social_links ?? [];
        $rawSocialLinks = isset($rawSocialLinks['url']) || isset($rawSocialLinks['href'])
            ? [$rawSocialLinks]
            : $rawSocialLinks;
        $personSocials = collect($rawSocialLinks)->map(function ($link, $key) {
            $data = is_array($link) ? $link : ['url' => $link];
            $url = trim((string) ($data['url'] ?? $data['href'] ?? ''));
            $platform = trim((string) ($data['platform'] ?? $data['label'] ?? (is_string($key) ? $key : 'Website')));
            $platform = $platform !== '' ? $platform : 'Website';
            $label = trim((string) ($data['label'] ?? ''));
            $label = $label !== '' ? $label : (string) str($platform)->headline();
            $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

            if (
                ! \App\Rules\SafeUrl::allows($url)
                || filter_var($url, FILTER_VALIDATE_URL) === false
                || ! in_array($scheme, ['http', 'https'], true)
            ) {
                return null;
            }

            $platformKey = strtolower(preg_replace('/[^a-z0-9]+/i', '', $platform) ?? '');
            $icon = [
                'facebook' => 'facebook',
                'instagram' => 'instagram',
                'tiktok' => 'tiktok',
                'twitter' => 'twitter',
                'x' => 'twitter',
                'youtube' => 'youtube',
            ][$platformKey] ?? 'arrow-right';

            return [
                'url' => $url,
                'label' => mb_substr($label, 0, 60),
                'icon' => $icon,
            ];
        })->filter()->values();
    @endphp
    <section class="container profile-layout">
        <div class="profile-media">
            @if($person->photo)
                <img src="{{ $person->photo->url('web') ?: $person->photo->url() }}" alt="{{ $person->photo->alt_text }}">
            @else
                <div class="media-placeholder"><span>Player image</span></div>
            @endif
        </div>
        <div class="profile-copy">
            <a href="{{ route('site.page', ['slug' => 'roster']) }}" wire:navigate>Back to roster</a>
            <span class="profile-number">#{{ $membership?->jersey_number }}</span>
            <h1>{{ $person->display_name }}</h1>
            <p class="article-lead">
                {{ $membership?->position }}
                @if($membership?->height) &middot; {{ $membership->height }} @endif
                @if($membership?->class_year) &middot; {{ $membership->class_year }} @endif
            </p>
            <p>{{ $person->biography ?: 'Player biography coming soon.' }}</p>
            @if ($personSocials->isNotEmpty())
                <div class="leader-socials" role="group" aria-label="{{ $person->display_name }} social links">
                    @foreach ($personSocials as $social)
                        <a href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $person->display_name }} on {{ $social['label'] }}">
                            <img src="{{ asset('assets/icons/'.$social['icon'].'.svg') }}" alt="" width="17" height="17" loading="lazy" decoding="async">
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</main>
