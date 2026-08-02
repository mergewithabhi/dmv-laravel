@foreach ($players as $membership)
@php
    $person = $membership->person;
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
<article class="player-card">
    @if ($person->photo)
        @php
            $playerThumb = $person->photo->url('thumb') ?: $person->photo->url();
            $playerWeb = $person->photo->url('web') ?: $person->photo->url();
        @endphp
        <img
            class="player-image"
            src="{{ $playerThumb }}"
            srcset="{{ $playerThumb }} 480w, {{ $playerWeb }} 1600w"
            sizes="(max-width: 680px) 45vw, 180px"
            alt="{{ $person->photo->alt_text }}"
            loading="lazy"
            decoding="async"
        >
    @else
        <div class="player-image media-placeholder"><span>Player</span></div>
    @endif
    <span class="jersey">{{ $membership->jersey_number }}</span><span class="position">{{ mb_substr($membership->position, 0, 1) }}</span>
    <h3><a href="{{ route('players.show', $person) }}">{{ $person->display_name }}</a></h3>
    @if ($personSocials->isNotEmpty())
        <div class="leader-socials" role="group" aria-label="{{ $person->display_name }} social links">
            @foreach ($personSocials as $social)
                <a href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $person->display_name }} on {{ $social['label'] }}">
                    <img src="{{ asset('assets/icons/'.$social['icon'].'.svg') }}" alt="" width="17" height="17" loading="lazy" decoding="async">
                </a>
            @endforeach
        </div>
    @endif
</article>
@endforeach
