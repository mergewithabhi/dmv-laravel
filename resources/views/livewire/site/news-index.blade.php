<main id="site-main" class="dynamic-page">
    <header class="content-page-hero"><div class="container"><span>DMV Warriors</span><h1>Latest News</h1><p>Team updates, community stories, and announcements.</p></div></header>
    <section class="container dynamic-content-section">
        <div class="dynamic-card-grid">
            @forelse($posts as $post)
                <article class="dynamic-card">
                    @if($post->featuredMedia)<img src="{{ $post->featuredMedia->url('thumb') ?: $post->featuredMedia->url() }}" alt="{{ $post->featuredMedia->alt_text }}">@else<div class="media-placeholder"><span>News image</span></div>@endif
                    <div><time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->format('M j, Y') }}</time><h2><a href="{{ route('news.show', $post) }}" wire:navigate>{{ $post->title }}</a></h2><p>{{ $post->excerpt }}</p></div>
                </article>
            @empty
                <p role="status">Team news and announcements are coming soon.</p>
            @endforelse
        </div>
        {{ $posts->links(data: ['scrollTo' => false]) }}
    </section>
</main>
