@if ($sponsors->isNotEmpty())
    <div class="partner-carousel-shell">
        <button class="partner-carousel-button previous" type="button" data-partner-previous aria-label="Previous partners">
            <img src="{{ asset('assets/icons/arrow-right.svg') }}" alt="">
        </button>

        <div class="partner-carousel-viewport" data-partner-viewport tabindex="0" aria-label="Partner logos">
            <div class="partner-carousel-track" data-partner-track>
                @foreach ($sponsors as $sponsor)
                    <article
                        class="partner-carousel-slide"
                        role="group"
                        aria-roledescription="slide"
                        aria-label="{{ $loop->iteration }} of {{ $sponsors->count() }}: {{ $sponsor->name }}"
                    >
                        @if ($sponsor->logo)
                            @if ($sponsor->website_url)
                                <a href="{{ $sponsor->website_url }}" target="_blank" rel="noopener noreferrer" aria-label="Visit {{ $sponsor->name }}">
                                    <img src="{{ $sponsor->logo->url('thumb') ?: $sponsor->logo->url() }}" alt="{{ $sponsor->logo->alt_text ?: $sponsor->name }}" loading="lazy" decoding="async">
                                </a>
                            @else
                                <img src="{{ $sponsor->logo->url('thumb') ?: $sponsor->logo->url() }}" alt="{{ $sponsor->logo->alt_text ?: $sponsor->name }}" loading="lazy" decoding="async">
                            @endif
                        @else
                            <strong>{{ $sponsor->name }}</strong>
                        @endif
                    </article>
                @endforeach
            </div>
        </div>

        <button class="partner-carousel-button next" type="button" data-partner-next aria-label="Next partners">
            <img src="{{ asset('assets/icons/arrow-right.svg') }}" alt="">
        </button>
    </div>
    <p class="sr-only" data-partner-status aria-live="off" aria-atomic="true"></p>
@else
    <p class="partner-carousel-empty">Partner announcements are coming soon.</p>
@endif
