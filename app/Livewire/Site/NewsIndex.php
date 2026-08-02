<?php

namespace App\Livewire\Site;

use App\Livewire\Site\Concerns\BuildsSiteLayoutData;
use App\Models\Post;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.site')]
class NewsIndex extends Component
{
    use BuildsSiteLayoutData, WithPagination;

    public function render()
    {
        $posts = Post::query()
            ->published()
            ->with(['category', 'featuredMedia'])
            ->latest('published_at')
            ->paginate(9);
        $meta = [
            'title' => 'News | DMV Warriors',
            'description' => 'DMV Warriors team news, announcements, and community stories.',
            'canonical' => route('news.index'),
            'page_key' => 'news',
        ];

        return view('livewire.site.news-index', compact('posts'))
            ->title($meta['title'])
            ->layoutData($this->siteLayoutData($meta, [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => 'DMV Warriors News',
                'url' => route('news.index'),
            ]));
    }
}
