<?php

namespace App\Livewire\Site;

use App\Livewire\Site\Concerns\BuildsSiteLayoutData;
use App\Models\Post;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.site')]
class NewsShow extends Component
{
    use BuildsSiteLayoutData;

    public Post $post;

    public function mount(Post $post): void
    {
        abort_unless(Post::query()->published()->whereKey($post)->exists(), 404);
        $this->post = $post->load(['category', 'featuredMedia', 'author']);
    }

    public function render()
    {
        $meta = [
            'title' => ($this->post->seo_title ?: $this->post->title).' | DMV Warriors',
            'description' => $this->post->seo_description ?: $this->post->excerpt,
            'canonical' => route('news.show', $this->post),
            'og_image' => $this->post->featuredMedia?->url('web'),
            'page_key' => 'news',
        ];

        return view('livewire.site.news-show')
            ->title($meta['title'])
            ->layoutData($this->siteLayoutData($meta, [
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                'headline' => $this->post->title,
                'datePublished' => $this->post->published_at?->toIso8601String(),
                'dateModified' => $this->post->updated_at->toIso8601String(),
                'description' => $meta['description'],
                'url' => $meta['canonical'],
            ]));
    }
}
