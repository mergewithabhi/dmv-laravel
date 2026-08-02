<div>
    <div class="admin-page-heading">
        <div><h2>Website pages</h2><p>Choose a page to update its text, buttons, and images.</p></div>
        <a class="admin-button secondary" href="{{ route('home') }}" target="_blank" rel="noopener">View live website</a>
    </div>

    <div class="page-card-toolbar">
        <label for="page-search">Find a page</label>
        <input id="page-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Search website pages">
    </div>

    <div class="page-card-grid">
        @forelse ($pages as $page)
            <article class="page-card" wire:key="page-{{ $page->id }}">
                <div class="page-card-preview">
                    <iframe src="{{ $this->previewUrl($page) }}" title="{{ $page->title }} page thumbnail" loading="lazy" tabindex="-1"></iframe>
                </div>
                <div class="page-card-body">
                    <span class="status-badge published">Live</span>
                    <h3>{{ $page->title }}</h3>
                    <p>{{ $page->slug === 'home' ? 'Website home page' : ucfirst($page->slug).' page' }}</p>
                    <small>Updated {{ $page->updated_at->diffForHumans() }}</small>
                    <div class="admin-actions">
                        <a class="admin-button" href="{{ route('admin.pages.edit', $page) }}" wire:navigate>Edit page</a>
                        <a class="admin-button secondary" href="{{ $page->slug === 'home' ? route('home') : route('site.page', ['slug' => $page->slug]) }}" target="_blank" rel="noopener">View live</a>
                    </div>
                </div>
            </article>
        @empty
            <div class="admin-panel admin-empty">No pages match your search.</div>
        @endforelse
    </div>

    @include('livewire.admin.partials.pagination', ['paginator' => $pages])
</div>
