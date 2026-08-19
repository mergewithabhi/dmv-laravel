<?php

namespace App\Livewire\Admin;

use App\Models\GalleryItem;
use App\Models\MediaAsset;
use App\Services\AdminMediaUploadService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
class GalleryManager extends Component
{
    use WithFileUploads, WithPagination;

    public bool $showEditor = false;

    #[Locked]
    public ?int $editingId = null;

    public array $form = [
        'media_asset_id' => null,
        'title' => '',
        'caption' => '',
        'alt_text' => '',
        'position' => 0,
        'is_published' => true,
    ];

    public array $mediaUploads = [];

    public array $uploads = [];

    public string $search = '';

    public string $typeFilter = '';

    public int $perPage = 15;

    public array $selected = [];

    public function mount(): void
    {
        $this->authorizeAccess();
    }

    public function create(): void
    {
        $this->authorizeAccess();
        $nextPosition = ((int) GalleryItem::query()->max('position')) + 10;
        $this->resetEditor();
        $this->form['position'] = min($nextPosition, 65535);
        $this->showEditor = true;
    }

    public function edit(int $id): void
    {
        $this->authorizeAccess();
        $item = GalleryItem::query()->findOrFail($id);
        $this->editingId = $item->id;
        $this->form = [
            'media_asset_id' => $item->media_asset_id,
            'title' => $item->title,
            'caption' => $item->caption ?? '',
            'alt_text' => $item->alt_text ?? '',
            'position' => $item->position,
            'is_published' => $item->is_published,
        ];
        $this->mediaUploads = [];
        $this->showEditor = true;
        $this->resetValidation();
    }

    public function cancelEditor(): void
    {
        $this->authorizeAccess();
        $this->resetEditor();
    }

    public function selectMedia(int $assetId): void
    {
        $this->authorizeAccess();
        $asset = MediaAsset::query()
            ->whereKey($assetId)
            ->whereIn('kind', ['image', 'video'])
            ->firstOrFail();
        $this->form['media_asset_id'] = $asset->id;
        if (trim((string) $this->form['title']) === '') {
            $this->form['title'] = $asset->title;
        }
        if (trim((string) $this->form['alt_text']) === '') {
            $this->form['alt_text'] = $asset->alt_text ?? '';
        }
        $this->resetValidation('form.media_asset_id');
    }

    public function clearMedia(): void
    {
        $this->authorizeAccess();
        $this->form['media_asset_id'] = null;
    }

    public function uploadMedia(AdminMediaUploadService $uploader): void
    {
        $this->authorizeAccess();
        $this->validate([
            'mediaUploads.editor' => [
                'required',
                'file',
                'max:102400',
                'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/quicktime,video/webm',
            ],
        ]);
        $kind = str_starts_with((string) $this->mediaUploads['editor']->getMimeType(), 'video/')
            ? 'video'
            : 'image';
        $asset = $uploader->store(
            $this->mediaUploads['editor'],
            $kind,
            trim((string) $this->form['alt_text']) ?: 'Gallery media'
        );
        $this->form['media_asset_id'] = $asset->id;
        $this->form['title'] = trim((string) $this->form['title']) ?: $asset->title;
        $this->form['alt_text'] = trim((string) $this->form['alt_text']) ?: ($asset->alt_text ?? '');
        unset($this->mediaUploads['editor']);
    }

    public function uploadImages(AdminMediaUploadService $uploader): void
    {
        $this->authorizeAccess();
        $this->validate([
            'uploads' => ['required', 'array', 'min:1', 'max:20'],
            'uploads.*' => [
                'required',
                'file',
                'max:102400',
                'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/quicktime,video/webm',
            ],
        ], [
            'uploads.required' => 'Choose at least one image or video.',
            'uploads.max' => 'Upload no more than 20 files at a time.',
            'uploads.*.max' => 'Each file must be 100 MB or smaller.',
            'uploads.*.mimetypes' => 'Use JPG, PNG, WebP, GIF, MP4, MOV, or WebM files.',
        ]);

        $nextPosition = ((int) GalleryItem::query()->max('position')) + 10;
        $created = collect();

        foreach ($this->uploads as $index => $upload) {
            $label = Str::of(pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME))
                ->replace(['-', '_'], ' ')
                ->squish()
                ->title()
                ->value() ?: 'Gallery media';
            $kind = str_starts_with((string) $upload->getMimeType(), 'video/') ? 'video' : 'image';
            $asset = $uploader->store($upload, $kind, $label);
            $item = GalleryItem::query()->create([
                'media_asset_id' => $asset->id,
                'title' => $asset->title,
                'alt_text' => $label,
                'position' => min($nextPosition + ($index * 10), 65535),
                'is_published' => true,
                'created_by' => auth()->id(),
            ]);
            activity('cms')
                ->causedBy(auth()->user())
                ->performedOn($item)
                ->log('added gallery item');
            $created->push($item);
        }

        $count = $created->count();
        $this->uploads = [];
        $this->resetValidation('uploads');
        session()->flash(
            'success',
            "{$count} gallery ".str('item')->plural($count).' uploaded and published.'
        );
    }

    public function save(): void
    {
        $this->authorizeAccess();
        $validated = $this->validate([
            'form.media_asset_id' => [
                'required',
                'integer',
                Rule::exists('media_assets', 'id')->where(
                    fn ($query) => $query->whereIn('kind', ['image', 'video'])
                ),
                Rule::unique('gallery_items', 'media_asset_id')->ignore($this->editingId),
            ],
            'form.title' => ['required', 'string', 'max:180'],
            'form.caption' => ['nullable', 'string', 'max:1000'],
            'form.alt_text' => ['required', 'string', 'max:500'],
            'form.position' => ['required', 'integer', 'between:0,65535'],
            'form.is_published' => ['boolean'],
        ], [
            'form.media_asset_id.required' => 'Choose or upload an image or video.',
            'form.media_asset_id.unique' => 'That file is already in the gallery.',
            'form.alt_text.required' => 'Describe the image or video for visitors using screen readers.',
        ])['form'];

        $item = $this->editingId
            ? GalleryItem::query()->findOrFail($this->editingId)
            : new GalleryItem(['created_by' => auth()->id()]);
        $item->fill([
            'media_asset_id' => (int) $validated['media_asset_id'],
            'title' => trim($validated['title']),
            'caption' => trim((string) ($validated['caption'] ?? '')) ?: null,
            'alt_text' => trim($validated['alt_text']),
            'position' => (int) $validated['position'],
            'is_published' => (bool) $validated['is_published'],
        ])->save();

        activity('cms')
            ->causedBy(auth()->user())
            ->performedOn($item)
            ->log($this->editingId ? 'updated gallery item' : 'added gallery item');
        $this->resetEditor();
        session()->flash('success', 'Gallery item saved.');
    }

    public function destroy(int $id): void
    {
        $this->authorizeAccess();
        $item = GalleryItem::query()->findOrFail($id);
        activity('cms')->causedBy(auth()->user())->performedOn($item)->log('deleted gallery item');
        $item->delete();
        $this->selected = collect($this->selected)
            ->reject(fn ($selectedId) => (int) $selectedId === $id)
            ->values()
            ->all();
        session()->flash('success', 'Gallery item removed.');
    }

    public function bulkPublish(): void
    {
        $this->bulkSetPublished(true);
    }

    public function bulkUnpublish(): void
    {
        $this->bulkSetPublished(false);
    }

    public function bulkDelete(): void
    {
        $this->authorizeAccess();
        $items = $this->selectedItems();
        DB::transaction(function () use ($items): void {
            $items->each(function (GalleryItem $item): void {
                activity('cms')->causedBy(auth()->user())->performedOn($item)->log('deleted gallery item');
                $item->delete();
            });
        });
        $count = $items->count();
        $this->selected = [];
        session()->flash('success', "{$count} gallery ".str('item')->plural($count).' removed.');
    }

    public function togglePageSelection(string $ids): void
    {
        $this->authorizeAccess();
        $pageIds = collect(explode(',', $ids))
            ->filter(fn (string $id) => ctype_digit($id))
            ->map(fn (string $id) => (int) $id)
            ->values();
        $selected = collect($this->selected)->map(fn ($id) => (int) $id);

        $this->selected = $pageIds->isNotEmpty() && $pageIds->every(fn (int $id) => $selected->contains($id))
            ? $selected->reject(fn (int $id) => $pageIds->contains($id))->values()->all()
            : $selected->merge($pageIds)->unique()->values()->all();
        $this->resetValidation('selected');
    }

    public function clearSelection(): void
    {
        $this->authorizeAccess();
        $this->selected = [];
        $this->resetValidation('selected');
    }

    public function updatedSearch(): void
    {
        $this->resetList();
    }

    public function updatedTypeFilter(): void
    {
        if (! in_array($this->typeFilter, ['', 'image', 'video'], true)) {
            $this->typeFilter = '';
        }
        $this->resetList();
    }

    public function updatedPerPage(): void
    {
        $this->authorizeAccess();
        if (! in_array($this->perPage, [10, 15, 25, 50], true)) {
            $this->perPage = 15;
        }
        $this->resetList();
    }

    public function render()
    {
        $this->authorizeAccess();
        $items = $this->itemsQuery()->paginate($this->perPage);
        $media = MediaAsset::query()
            ->with('media')
            ->whereIn('kind', ['image', 'video'])
            ->orderBy('title')
            ->get();

        return view('livewire.admin.gallery-manager', compact('items', 'media'))
            ->title('Gallery')
            ->layoutData(['heading' => 'Gallery']);
    }

    private function itemsQuery(): Builder
    {
        return GalleryItem::query()
            ->with('mediaAsset.media')
            ->when($this->search, function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $query->where('title', 'like', '%'.$this->search.'%')
                        ->orWhere('caption', 'like', '%'.$this->search.'%')
                        ->orWhereHas(
                            'mediaAsset',
                            fn (Builder $media) => $media->where('title', 'like', '%'.$this->search.'%')
                        );
                });
            })
            ->when(
                $this->typeFilter !== '',
                fn (Builder $query) => $query->whereHas(
                    'mediaAsset',
                    fn (Builder $media) => $media->where('kind', $this->typeFilter)
                )
            )
            ->orderBy('position')
            ->latest('id');
    }

    private function bulkSetPublished(bool $published): void
    {
        $this->authorizeAccess();
        $items = $this->selectedItems();
        GalleryItem::query()->whereKey($items->modelKeys())->update([
            'is_published' => $published,
            'updated_at' => now(),
        ]);
        $items->each(
            fn (GalleryItem $item) => activity('cms')
                ->causedBy(auth()->user())
                ->performedOn($item)
                ->log($published ? 'published gallery item' : 'unpublished gallery item')
        );
        $count = $items->count();
        $this->selected = [];
        session()->flash('success', "{$count} gallery ".str('item')->plural($count).' '.($published ? 'published.' : 'hidden.'));
    }

    private function selectedItems()
    {
        $ids = collect($this->selected)->map(fn ($id) => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            throw ValidationException::withMessages(['selected' => 'Select at least one gallery item.']);
        }
        $items = GalleryItem::query()->whereKey($ids)->get();
        if ($items->count() !== $ids->count()) {
            throw ValidationException::withMessages(['selected' => 'One or more selected images no longer exist.']);
        }

        return $items;
    }

    private function resetEditor(): void
    {
        $this->showEditor = false;
        $this->editingId = null;
        $this->form = [
            'media_asset_id' => null,
            'title' => '',
            'caption' => '',
            'alt_text' => '',
            'position' => 0,
            'is_published' => true,
        ];
        $this->mediaUploads = [];
        $this->resetValidation();
    }

    private function resetList(): void
    {
        $this->authorizeAccess();
        $this->selected = [];
        $this->resetValidation('selected');
        $this->resetPage();
    }

    private function authorizeAccess(): void
    {
        abort_unless(auth()->user()?->can('manage media'), 403);
    }
}
