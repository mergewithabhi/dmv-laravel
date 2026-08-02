<main id="site-main" class="dynamic-page">
    <article class="container article-layout">
        <header>
            <a href="{{ route('news.index') }}" wire:navigate>Back to news</a>
            <p>{{ $post->category?->name }} &middot; {{ $post->published_at?->format('F j, Y') }}</p>
            <h1>{{ $post->title }}</h1>
            <p class="article-lead">{{ $post->excerpt }}</p>
        </header>
        @if($post->featuredMedia)
            <img class="article-media" src="{{ $post->featuredMedia->url('web') ?: $post->featuredMedia->url() }}" alt="{{ $post->featuredMedia->alt_text }}">
        @endif
        <div class="article-body">{!! nl2br(e($post->body)) !!}</div>
    </article>
</main>
