@php
    use App\Livewire\Site\Support\PublicInteractionNormalizer;

    $logoId = $settings['branding.footer_logo_media_id'] ?? $settings['branding.logo_media_id'] ?? null;
    $logoAsset = $logoId ? $settingMedia->get((int) $logoId) : null;
    $logo = $logoAsset?->url('thumb') ?: $logoAsset?->url();
    $marquee = $settings['footer.marquee_text'] ?? 'DMV';
    $marqueeSpeed = max(10, min(120, (int) ($settings['footer.marquee_speed'] ?? 40)));
    $homeUrl = PublicInteractionNormalizer::url('/#home');
    $footerLinkText = trim((string) ($settings['footer.link_text'] ?? ''));
    $footerLinkUrl = trim((string) ($settings['footer.link_url'] ?? ''));
    $footerLinkUrl = $footerLinkUrl !== '' ? PublicInteractionNormalizer::url($footerLinkUrl) : '';
@endphp
<footer class="site-footer" id="footer">
    <div class="footer-banner">
        <div class="container">
            <strong>{{ $settings['branding.site_name'] ?? 'DMV Warriors Basketball' }}</strong>
            <span>{{ $settings['footer.region_one'] ?? 'Washington D.C.' }}</span><i></i>
            <span>{{ $settings['footer.region_two'] ?? 'Maryland' }}</span><i></i>
            <span>{{ $settings['footer.region_three'] ?? 'Virginia' }}</span>
        </div>
    </div>
    <div class="footer-watermark" aria-hidden="true" style="--footer-marquee-duration: {{ $marqueeSpeed }}s">
        <div class="footer-watermark-track">
            @for ($group = 0; $group < 2; $group++)
                <div class="footer-watermark-group">
                    @for ($item = 0; $item < 4; $item++)<span>{{ $marquee }}</span>@endfor
                </div>
            @endfor
        </div>
    </div>
    <div class="container footer-grid" data-stagger>
        <div class="footer-brand">
            <a class="brand brand-light" href="{{ $homeUrl }}" wire:navigate>
                <img
                    src="{{ $logo ?: asset('assets/bmv_logo_transparent.png') }}"
                    alt="{{ $settings['branding.site_name'] ?? 'DMV Warriors' }}"
                    width="1024"
                    height="1024"
                    loading="lazy"
                    decoding="async"
                >
            </a>
            <p class="footer-motto">{{ $settings['footer.motto'] ?? 'Built in the DMV. Earned on the Court.' }}</p>
            <p>{{ $settings['footer.description'] ?? 'The DMV Warriors are more than a team. We are a movement.' }}</p>
            <a class="footer-schedule" href="{{ route('site.page', ['slug' => 'schedule']) }}" wire:navigate>
                {{ $settings['footer.schedule_label'] ?? 'View Schedule' }}
                <img src="{{ asset('assets/icons/arrow-right.svg') }}" alt="">
            </a>
        </div>
        <div class="footer-links">
            <h2>{{ $settings['footer.navigation_heading'] ?? 'Navigation' }}</h2>
            <div>
                @foreach ($footerNavigation as $item)
                    @php($itemUrl = PublicInteractionNormalizer::url($item->url))
                    <a
                        href="{{ $itemUrl }}"
                        @if(PublicInteractionNormalizer::isInternal($itemUrl) && $item->target !== '_blank') wire:navigate @endif
                        @if($item->target === '_blank') target="_blank" rel="noopener noreferrer" @endif
                    >{{ $item->label }}</a>
                @endforeach
            </div>
        </div>
        <div class="footer-contact" id="contact">
            <h2>{{ $settings['footer.contact_heading'] ?? 'Contact' }}</h2>
            <a href="mailto:{{ $settings['contact.email'] ?? 'info@dmvwarriors.com' }}"><img src="{{ asset('assets/icons/mail.svg') }}" alt="">{{ $settings['contact.email'] ?? 'info@dmvwarriors.com' }}</a>
            <a href="tel:{{ preg_replace('/[^+\d]/', '', $settings['contact.phone'] ?? '') }}"><img src="{{ asset('assets/icons/phone.svg') }}" alt="">{{ $settings['contact.phone'] ?? '(301) 555-0198' }}</a>
            <p><img src="{{ asset('assets/icons/pin.svg') }}" alt="">{{ $settings['contact.address_short'] ?? "Prince George's Sports & Learning Complex, Landover, MD" }}</p>
        </div>
        <div class="footer-social">
            <h2>{{ $settings['footer.social_heading'] ?? 'Follow Us' }}</h2>
            <div>
                @foreach ($socialLinks as $social)
                    @php($socialUrl = trim((string) $social->url))
                    @if ($socialUrl !== '' && $socialUrl !== '#')
                        <a href="{{ $socialUrl }}" aria-label="{{ $social->label }}" target="_blank" rel="noopener noreferrer">
                            <img src="{{ $social->icon?->url() ?: asset('assets/icons/'.strtolower($social->platform).'.svg') }}" alt="" width="24" height="24" loading="lazy" decoding="async">
                        </a>
                    @else
                        <span class="footer-social-unavailable" aria-label="{{ $social->label }} link unavailable">
                            <img src="{{ $social->icon?->url() ?: asset('assets/icons/'.strtolower($social->platform).'.svg') }}" alt="" width="24" height="24" loading="lazy" decoding="async">
                        </span>
                    @endif
                @endforeach
            </div>
            <p>{{ $settings['footer.social_copy'] ?? 'Join the Warriors community.' }}</p>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="container">
            <p>&copy; <span data-current-year>{{ now()->year }}</span> {{ $settings['footer.copyright'] ?? 'DMV Warriors Basketball. All Rights Reserved.' }}</p>
            <p>
                @if ($footerLinkText === '' && $footerLinkUrl !== '' && $footerLinkUrl !== '#')
                    <a
                        href="{{ $footerLinkUrl }}"
                        @if(PublicInteractionNormalizer::isInternal($footerLinkUrl))
                            wire:navigate
                        @else
                            target="_blank"
                            rel="noopener noreferrer"
                        @endif
                    >{{ $settings['footer.values'] ?? 'Discipline. Teamwork. Community. Excellence.' }}</a>
                @else
                    {{ $settings['footer.values'] ?? 'Discipline. Teamwork. Community. Excellence.' }}
                    @if ($footerLinkText !== '' && $footerLinkUrl !== '' && $footerLinkUrl !== '#')
                        <a
                            href="{{ $footerLinkUrl }}"
                            @if(PublicInteractionNormalizer::isInternal($footerLinkUrl))
                                wire:navigate
                            @else
                                target="_blank"
                                rel="noopener noreferrer"
                            @endif
                        >{{ $footerLinkText }}</a>
                    @endif
                @endif
            </p>
        </div>
    </div>
</footer>
