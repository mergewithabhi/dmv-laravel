@foreach ($staff as $assignment)
@php
    $person = $assignment->person;
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
<article>
    @if($person->photo)
        @php
            $coachThumb = $person->photo->url('thumb') ?: $person->photo->url();
            $coachWeb = $person->photo->url('web') ?: $person->photo->url();
        @endphp
        <img
            class="coach-photo"
            src="{{ $coachThumb }}"
            srcset="{{ $coachThumb }} 480w, {{ $coachWeb }} 1600w"
            sizes="(max-width: 680px) calc(100vw - 56px), 280px"
            alt="{{ $person->photo->alt_text }}"
            loading="lazy"
            decoding="async"
        >
    @else
        <div class="coach-photo media-placeholder"><span>Coach image</span></div>
    @endif
    <div class="coach-copy">
        <h3>{{ $person->display_name }}</h3><strong>{{ $assignment->role }}</strong>
        <p>{{ $person->biography }}</p>
        @if ($personSocials->isNotEmpty())
            <div aria-label="{{ $person->display_name }} social links">
                @foreach ($personSocials as $social)
                    <a href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $person->display_name }} on {{ $social['label'] }}">
                        <img src="{{ asset('assets/icons/'.$social['icon'].'.svg') }}" alt="" width="17" height="17" loading="lazy" decoding="async">
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</article>
@endforeach
