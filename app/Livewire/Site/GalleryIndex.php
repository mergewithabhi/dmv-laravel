<?php

namespace App\Livewire\Site;

use App\Livewire\Site\Concerns\BuildsSiteLayoutData;
use App\Models\GalleryItem;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.site')]
class GalleryIndex extends Component
{
    use BuildsSiteLayoutData, WithPagination;

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: 'recent')]
    public string $sort = 'recent';

    public function mount(): void
    {
        $this->normalizeFilters();
    }

    public function updatedType(): void
    {
        $this->normalizeFilters();
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->normalizeFilters();
        $this->resetPage();
    }

    public function render()
    {
        $this->normalizeFilters();

        $items = GalleryItem::query()
            ->published()
            ->with('mediaAsset.media')
            ->when(
                $this->type !== '',
                fn (Builder $query) => $query->whereHas(
                    'mediaAsset',
                    fn (Builder $media) => $media->where('kind', $this->type)
                )
            )
            ->when(
                $this->sort === 'oldest',
                fn (Builder $query) => $query->oldest('created_at')->oldest('id'),
                fn (Builder $query) => $query->latest('created_at')->latest('id')
            )
            ->paginate(9);
        $meta = [
            'title' => 'Gallery | DMV Warriors',
            'description' => 'Photos from DMV Warriors games, team events, and community programs.',
            'canonical' => route('gallery.index'),
            'page_key' => 'gallery',
        ];

        return view('livewire.site.gallery-index', compact('items'))
            ->title($meta['title'])
            ->layoutData($this->siteLayoutData($meta, [
                '@context' => 'https://schema.org',
                '@type' => 'ImageGallery',
                'name' => 'DMV Warriors Gallery',
                'url' => route('gallery.index'),
            ]));
    }

    private function normalizeFilters(): void
    {
        if (! in_array($this->type, ['', 'image', 'video'], true)) {
            $this->type = '';
        }
        if (! in_array($this->sort, ['recent', 'oldest'], true)) {
            $this->sort = 'recent';
        }
    }
}
