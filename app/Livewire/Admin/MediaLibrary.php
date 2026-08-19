<?php

namespace App\Livewire\Admin;

use App\Models\MediaAsset;
use App\Models\GalleryItem;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Person;
use App\Models\Post;
use App\Models\SiteSetting;
use App\Models\SocialLink;
use App\Models\Sponsor;
use App\Models\Team;
use App\Models\Venue;
use enshrined\svgSanitize\Sanitizer;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
class MediaLibrary extends Component
{
    use WithFileUploads, WithPagination;

    public $upload;

    public string $title = '';

    public string $altText = '';

    public string $kind = 'image';

    public bool $isDecorative = false;

    public string $search = '';

    public string $kindFilter = '';

    #[Locked]
    public ?int $editingId = null;

    public array $metadata = [];

    public function mount(): void
    {
        $this->authorizeAccess();
    }

    public function updatedSearch(): void
    {
        $this->authorizeAccess();
        $this->resetPage();
    }

    public function updatedKindFilter(): void
    {
        $this->authorizeAccess();
        $this->resetPage();
    }

    public function uploadAsset(): void
    {
        $this->authorizeAccess();
        $this->validate([
            'upload' => ['required', 'file', 'max:'.config('cms.max_upload_kilobytes')],
            'title' => ['required', 'string', 'max:180'],
            'altText' => [$this->isDecorative || $this->kind !== 'image' ? 'nullable' : 'required', 'string', 'max:500'],
            'kind' => ['required', 'in:image,icon,video,document'],
            'isDecorative' => ['boolean'],
        ]);

        $mime = (string) $this->upload->getMimeType();
        $allowed = config('cms.allowed_upload_mimes');
        $originalExtension = strtolower($this->upload->getClientOriginalExtension());
        $isSvg = $mime === 'image/svg+xml'
            || (
                $originalExtension === 'svg'
                && in_array($mime, ['text/plain', 'application/xml', 'text/xml'], true)
            );
        abort_unless(in_array($mime, $allowed, true) || $isSvg, 422, 'Unsupported file type.');

        $kindMatchesMime = match ($this->kind) {
            'image' => str_starts_with($mime, 'image/') && ! $isSvg,
            'icon' => str_starts_with($mime, 'image/') || $isSvg,
            'video' => str_starts_with($mime, 'video/'),
            'document' => in_array($mime, ['application/pdf', 'text/calendar'], true),
            default => false,
        };
        abort_unless($kindMatchesMime, 422, 'The selected asset type does not match the uploaded file.');

        $extension = match (true) {
            $isSvg => 'svg',
            $mime === 'image/jpeg' => 'jpg',
            $mime === 'image/png' => 'png',
            $mime === 'image/webp' => 'webp',
            $mime === 'image/gif' => 'gif',
            $mime === 'video/mp4' => 'mp4',
            $mime === 'video/quicktime' => 'mov',
            $mime === 'video/webm' => 'webm',
            $mime === 'application/pdf' => 'pdf',
            $mime === 'text/calendar' => 'ics',
            default => abort(422, 'Unsupported file type.'),
        };

        if ($this->kind === 'image') {
            abort_unless(@getimagesize($this->upload->getRealPath()) !== false, 422, 'The image is malformed.');
        }

        $asset = MediaAsset::query()->create([
            'uuid' => (string) Str::uuid(),
            'kind' => $this->kind,
            'title' => $this->title,
            'alt_text' => $this->isDecorative ? null : $this->altText,
            'is_decorative' => $this->isDecorative,
            'created_by' => auth()->id(),
        ]);

        $baseName = Str::slug(pathinfo($this->upload->getClientOriginalName(), PATHINFO_FILENAME))
            ?: (string) Str::uuid();
        $safeFileName = $baseName.'.'.$extension;

        if ($isSvg) {
            $sanitized = (new Sanitizer)->sanitize(file_get_contents($this->upload->getRealPath()));
            if ($sanitized === false) {
                $asset->delete();
                $this->addError('upload', 'The SVG could not be sanitized.');

                return;
            }
            $asset->addMediaFromString($sanitized)
                ->usingFileName($safeFileName)
                ->toMediaCollection('file');
        } else {
            $asset->addMedia($this->upload->getRealPath())
                ->usingFileName($safeFileName)
                ->toMediaCollection('file');
        }

        activity('cms')->causedBy(auth()->user())->performedOn($asset)->log('uploaded media');
        $this->reset(['upload', 'title', 'altText', 'isDecorative']);
        $this->kind = 'image';
        session()->flash('success', 'Media uploaded successfully.');
    }

    public function edit(int $id): void
    {
        $this->authorizeAccess();
        $asset = MediaAsset::query()->findOrFail($id);
        $this->editingId = $id;
        $this->metadata = [
            'title' => $asset->title,
            'alt_text' => $asset->alt_text,
            'caption' => $asset->caption,
            'credit' => $asset->credit,
            'kind' => $asset->kind->value,
            'focal_x' => $asset->focal_x,
            'focal_y' => $asset->focal_y,
            'is_decorative' => $asset->is_decorative,
        ];
        $this->resetValidation('metadata');
    }

    public function cancelEdit(): void
    {
        $this->authorizeAccess();
        $this->editingId = null;
        $this->metadata = [];
        $this->resetValidation('metadata');
    }

    public function saveMetadata(): void
    {
        $this->authorizeAccess();
        abort_unless($this->editingId, 422, 'Select a media asset before saving metadata.');
        $validated = $this->validate([
            'metadata.title' => ['required', 'string', 'max:180'],
            'metadata.alt_text' => [$this->metadata['is_decorative'] ?? false ? 'nullable' : 'required', 'string', 'max:500'],
            'metadata.caption' => ['nullable', 'string', 'max:1000'],
            'metadata.credit' => ['nullable', 'string', 'max:180'],
            'metadata.kind' => ['required', 'in:image,icon,video,document'],
            'metadata.focal_x' => ['required', 'numeric', 'between:0,100'],
            'metadata.focal_y' => ['required', 'numeric', 'between:0,100'],
            'metadata.is_decorative' => ['boolean'],
        ])['metadata'];

        if ($validated['is_decorative']) {
            $validated['alt_text'] = null;
        }

        $asset = MediaAsset::query()->findOrFail($this->editingId);
        $mime = (string) $asset->getFirstMedia('file')?->mime_type;
        $kindMatchesMime = match ($validated['kind']) {
            'image' => str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml',
            'icon' => str_starts_with($mime, 'image/'),
            'video' => str_starts_with($mime, 'video/'),
            'document' => in_array($mime, ['application/pdf', 'text/calendar'], true),
            default => false,
        };
        if (! $kindMatchesMime) {
            $this->addError('metadata.kind', 'The asset type does not match the stored file.');

            return;
        }

        $asset->update($validated);
        activity('cms')->causedBy(auth()->user())->performedOn($asset)->log('updated media metadata');
        $this->editingId = null;
        $this->metadata = [];
        session()->flash('success', 'Media metadata was saved.');
    }

    public function destroy(int $id): void
    {
        $this->authorizeAccess();
        $asset = MediaAsset::query()->findOrFail($id);
        abort_if($this->isUsed($asset), 422, 'This media asset is currently in use.');
        activity('cms')->causedBy(auth()->user())->performedOn($asset)->log('deleted media');
        $asset->delete();
        session()->flash('success', 'Unused media was deleted.');
    }

    public function render()
    {
        $this->authorizeAccess();
        $assets = MediaAsset::query()
            ->with('media')
            ->when($this->search, fn ($query) => $query->where('title', 'like', '%'.$this->search.'%'))
            ->when($this->kindFilter, fn ($query) => $query->where('kind', $this->kindFilter))
            ->latest()
            ->paginate(24);

        return view('livewire.admin.media-library', compact('assets'))
            ->title('Media Library')
            ->layoutData(['heading' => 'Media Library']);
    }

    private function isUsed(MediaAsset $asset): bool
    {
        $explicit = [
            Page::query()->where('og_media_id', $asset->id)->exists(),
            Person::query()->where('photo_media_id', $asset->id)->exists(),
            Team::query()->where('logo_media_id', $asset->id)->exists(),
            Venue::query()->where('image_media_id', $asset->id)->exists(),
            Post::query()->where('featured_media_id', $asset->id)->exists(),
            Sponsor::query()->where('logo_media_id', $asset->id)->exists(),
            NavigationItem::query()->where('icon_media_id', $asset->id)->exists(),
            SocialLink::query()->where('icon_media_id', $asset->id)->exists(),
            SiteSetting::query()
                ->where('type', 'media')
                ->get(['value'])
                ->contains(fn (SiteSetting $setting): bool => (int) ($setting->value['value'] ?? 0) === $asset->id),
            GalleryItem::query()->where('media_asset_id', $asset->id)->exists(),
        ];

        if (in_array(true, $explicit, true)) {
            return true;
        }

        return PageSection::query()->get(['field_schema', 'payload'])->contains(function (PageSection $section) use ($asset): bool {
            foreach (($section->field_schema['fields'] ?? []) as $fieldId => $field) {
                if (
                    in_array($field['input'] ?? null, ['media', 'icon'], true)
                    && (int) ($section->payload[$fieldId] ?? 0) === $asset->id
                ) {
                    return true;
                }
            }

            return false;
        }) || $this->isUsedByPageDraft($asset) || $this->isUsedByResourceDraft($asset);
    }

    private function isUsedByPageDraft(MediaAsset $asset): bool
    {
        return Page::query()
            ->whereNotNull('draft_snapshot')
            ->with('sections:id,page_id,field_schema')
            ->get(['id', 'draft_snapshot'])
            ->contains(function (Page $page) use ($asset): bool {
                $snapshot = $page->draft_snapshot ?? [];
                if ((int) data_get($snapshot, 'page.og_media_id', 0) === $asset->id) {
                    return true;
                }

                foreach ($page->sections as $section) {
                    foreach (($section->field_schema['fields'] ?? []) as $fieldId => $field) {
                        if (
                            in_array($field['input'] ?? null, ['media', 'icon'], true)
                            && (int) data_get($snapshot, "sections.{$section->id}.payload.{$fieldId}", 0) === $asset->id
                        ) {
                            return true;
                        }
                    }
                }

                return false;
            });
    }

    private function isUsedByResourceDraft(MediaAsset $asset): bool
    {
        $references = [
            Person::class => 'photo_media_id',
            Team::class => 'logo_media_id',
            Venue::class => 'image_media_id',
            Post::class => 'featured_media_id',
            Sponsor::class => 'logo_media_id',
        ];

        foreach ($references as $modelClass => $field) {
            if ($modelClass::query()
                ->whereNotNull('draft_snapshot')
                ->get(['draft_snapshot'])
                ->contains(
                    fn ($model): bool => (int) data_get(
                        $model->draft_snapshot,
                        $field,
                        0
                    ) === $asset->id
                )) {
                return true;
            }
        }

        return false;
    }

    private function authorizeAccess(): void
    {
        abort_unless(auth()->user()?->can('manage media'), 403);
    }
}
