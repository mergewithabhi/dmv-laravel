<div class="instagram-carousel" data-instagram-carousel>
    <div class="instagram-carousel-shell">
        <button class="instagram-carousel-button previous" type="button" data-instagram-previous aria-label="Previous Instagram posts">
            <img src="{{ asset('assets/icons/arrow-right.svg') }}" alt="">
        </button>

        <div class="instagram-carousel-viewport" data-instagram-viewport tabindex="0" aria-label="Recent Instagram posts">
            <div class="instagram-carousel-track" data-instagram-track>
                @foreach ($posts as $post)
                    <a
                        class="instagram-post instagram-carousel-slide"
                        href="{{ $post['permalink'] }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        role="group"
                        aria-roledescription="slide"
                        aria-label="{{ $loop->iteration }} of {{ $posts->count() }}{{ $post['caption'] !== '' ? ': '.\Illuminate\Support\Str::limit($post['caption'], 80) : '' }}"
                    >
                        <img
                            src="{{ $post['image_url'] }}"
                            alt="{{ $post['caption'] !== '' ? \Illuminate\Support\Str::limit($post['caption'], 120) : 'DMV Warriors Instagram post' }}"
                            loading="lazy"
                            decoding="async"
                            referrerpolicy="no-referrer"
                        >
                        @if ($post['media_type'] === 'VIDEO')
                            <span class="instagram-post-video" aria-hidden="true"></span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>

        <button class="instagram-carousel-button next" type="button" data-instagram-next aria-label="Next Instagram posts">
            <img src="{{ asset('assets/icons/arrow-right.svg') }}" alt="">
        </button>
    </div>
    <p class="sr-only" data-instagram-status aria-live="off" aria-atomic="true"></p>
</div>
