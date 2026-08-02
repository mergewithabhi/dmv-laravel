@foreach ($posts as $post)
<article class="news-card">
    @if ($post->featuredMedia)
        @php
            $newsThumb = $post->featuredMedia->url('thumb') ?: $post->featuredMedia->url();
            $newsWeb = $post->featuredMedia->url('web') ?: $post->featuredMedia->url();
        @endphp
        <img
            class="news-image"
            src="{{ $newsThumb }}"
            srcset="{{ $newsThumb }} 480w, {{ $newsWeb }} 1600w"
            sizes="(max-width: 680px) calc(100vw - 56px), (max-width: 1000px) 30vw, 350px"
            alt="{{ $post->featuredMedia->alt_text }}"
            loading="lazy"
            decoding="async"
        >
    @else
        <div class="news-image media-placeholder" role="img" aria-label="News image placeholder"><span>News image</span></div>
    @endif
    <div class="news-copy">
        <time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->format('M j, Y') }}</time>
        <h3>{{ $post->title }}</h3>
        <p>{{ $post->excerpt }}</p>
        <a href="{{ route('news.show', $post) }}">Read More <img src="{{ asset('assets/icons/arrow-right.svg') }}" alt=""></a>
    </div>
</article>
@endforeach
