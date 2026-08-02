<?php

namespace App\Livewire\Admin;

use App\Domain\Content\FieldGroupClassifier;
use App\Domain\Content\PageEditorSchema;
use App\Models\ContentRevision;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\PageSection;
use App\Rules\SafeUrl;
use App\Services\ContentPermissionGate;
use App\Services\AdminMediaUploadService;
use App\Services\PageWorkflowService;
use App\Services\SiteChromeService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.admin')]
class PageEditor extends Component
{
    use WithFileUploads;

    public Page $page;

    public array $pageForm = [];

    public array $sections = [];

    public array $mediaUploads = [];

    public string $previewToken = '';

    public bool $hasUnsavedChanges = false;

    #[Locked]
    public int $originalLockVersion;

    public ?string $publishAt = null;

    protected ContentPermissionGate $gate;

    protected FieldGroupClassifier $classifier;

    protected PageEditorSchema $editorSchema;

    public function boot(
        ContentPermissionGate $gate,
        FieldGroupClassifier $classifier,
        PageEditorSchema $editorSchema
    ): void
    {
        $this->gate = $gate;
        $this->classifier = $classifier;
        $this->editorSchema = $editorSchema;
    }

    public function mount(Page $page, PageWorkflowService $workflow): void
    {
        $this->page = $page->refresh()->load('sections');
        $this->authorizeAccess();
        $this->previewToken = (string) Str::uuid();
        $this->loadEditorState($workflow);
        $this->cachePreview();
    }

    private function loadEditorState(PageWorkflowService $workflow): void
    {
        $snapshot = $this->page->draft_snapshot ?: $workflow->snapshot($this->page);
        $this->originalLockVersion = (int) $this->page->draft_lock_version;
        $this->publishAt = $this->page->workflow_status->value === 'scheduled'
            ? $this->page->publish_at?->format('Y-m-d\TH:i')
            : null;
        $this->pageForm = $snapshot['page'];
        $this->sections = $snapshot['sections'];
        foreach ($this->page->sections as $section) {
            foreach (($section->field_schema['fields'] ?? []) as $fieldId => $field) {
                $value = $this->sections[$section->id]['payload'][$fieldId] ?? null;
                if (! is_string($value)) {
                    continue;
                }
                $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
                if (($field['input'] ?? null) === 'url') {
                    $path = '/'.ltrim($value, '/');
                    $value = config('cms.legacy_redirects', [])[$path] ?? $value;
                }
                $this->sections[$section->id]['payload'][$fieldId] = $value;
            }
        }
    }

    public function save(SiteChromeService $chrome, PageWorkflowService $workflow): void
    {
        $this->authorizeAccess();
        $this->page = $workflow->publishDirect(
            $this->page,
            $this->validatedSnapshot(),
            $this->originalLockVersion,
            auth()->user()
        );
        $this->loadEditorState($workflow);
        $this->hasUnsavedChanges = false;
        $chrome->forget();
        $this->cachePreview();
        activity('cms')->causedBy(auth()->user())->performedOn($this->page)->log('published page changes');
        session()->flash('success', 'Your changes are live.');
        $this->dispatch('cms-page-saved');
    }

    public function updated(string $property): void
    {
        if (! str_starts_with($property, 'pageForm.') && ! str_starts_with($property, 'sections.')) {
            return;
        }

        $this->hasUnsavedChanges = true;
        $this->cachePreview();
        $this->dispatch('cms-preview-refresh');
    }

    public function submit(SiteChromeService $chrome, PageWorkflowService $workflow): void
    {
        $this->authorizeAccess();
        $snapshot = $this->validatedSnapshot();

        if (! $this->page->draft_snapshot || $snapshot != $this->normalizedDraftSnapshot()) {
            $this->stageSnapshot($snapshot, $chrome, $workflow);
        }

        $this->page = $workflow->submit($this->page, auth()->user());
        $this->loadEditorState($workflow);
        session()->flash('success', 'Page submitted for review.');
    }

    public function publish(SiteChromeService $chrome, PageWorkflowService $workflow): void
    {
        $this->authorizeAccess();
        abort_unless($this->canEditPageSettings(), 403);

        if ($this->validatedSnapshot() != $this->normalizedDraftSnapshot()) {
            throw ValidationException::withMessages([
                'pageForm' => 'This review contains unsaved changes. Save and resubmit them before publishing.',
            ]);
        }

        $validated = $this->validate([
            'publishAt' => ['nullable', 'date', 'after:now'],
        ]);
        $publishAt = filled($validated['publishAt'])
            ? Carbon::parse($validated['publishAt'])
            : null;

        $this->page = $workflow->approve($this->page, auth()->user(), $publishAt);
        $this->loadEditorState($workflow);
        $chrome->forget();
        session()->flash(
            'success',
            $publishAt ? 'Page scheduled for '.$publishAt->format('M j, Y g:i A').'.' : 'Page published.'
        );
    }

    private function validatedSnapshot(): array
    {
        $user = auth()->user();
        $editsWholePage = $this->gate->isUnrestricted($user);

        $rules = [];
        if ($editsWholePage) {
            $rules['pageForm.title'] = ['required', 'string', 'max:180'];
            $rules['pageForm.seo_title'] = ['nullable', 'string', 'max:220'];
            $rules['pageForm.seo_description'] = ['nullable', 'string', 'max:500'];
            $rules['pageForm.canonical_url'] = ['nullable', 'url', 'max:2048', new SafeUrl];
            $rules['pageForm.og_media_id'] = ['nullable', 'integer', 'exists:media_assets,id'];
            $rules['pageForm.is_indexable'] = ['nullable', 'boolean'];
        }

        foreach ($this->page->sections as $section) {
            if ($this->gate->allowsSection($user, $this->page->template_key, $section->section_key)) {
                $rules["sections.{$section->id}.is_enabled"] = ['boolean'];
            }

            foreach (($section->field_schema['fields'] ?? []) as $fieldId => $field) {
                $fieldGroup = $this->classifier->classify($field, $section->section_key);
                if (! $this->gate->allows($user, $this->page->template_key, $section->section_key, $fieldGroup)) {
                    continue;
                }

                $fieldRules = ['nullable'];
                if (in_array($field['input'] ?? '', ['media', 'icon'], true)) {
                    $fieldRules = ['nullable', 'integer', 'exists:media_assets,id'];
                } elseif (($field['input'] ?? '') === 'url') {
                    $fieldRules = ['nullable', 'string', 'max:2048', new SafeUrl];
                } else {
                    $fieldRules[] = 'string';
                    $fieldRules[] = 'max:'.($field['max'] ?? 5000);
                }
                $rules["sections.{$section->id}.payload.{$fieldId}"] = $fieldRules;
            }
        }

        $validated = $this->validate($rules);
        $baseline = $this->page->draft_snapshot ?: [
            'page' => [
                'title' => $this->page->title,
                'seo_title' => $this->page->seo_title,
                'seo_description' => $this->page->seo_description,
                'canonical_url' => $this->page->canonical_url,
                'og_media_id' => $this->page->og_media_id,
                'is_indexable' => $this->page->is_indexable,
            ],
            'sections' => $this->page->sections->mapWithKeys(fn ($section) => [
                $section->id => ['is_enabled' => $section->is_enabled, 'payload' => $section->payload],
            ])->all(),
        ];

        $page = $editsWholePage ? $validated['pageForm'] ?? [] : ($baseline['page'] ?? []);
        $page['is_indexable'] = (bool) ($page['is_indexable'] ?? $this->page->is_indexable);

        $sections = [];
        foreach ($this->page->sections as $section) {
            $baselineSection = $baseline['sections'][$section->id] ?? [
                'is_enabled' => $section->is_enabled,
                'payload' => $section->payload,
            ];
            $submittedSection = $validated['sections'][$section->id] ?? [];

            $sections[$section->id] = [
                'is_enabled' => array_key_exists('is_enabled', $submittedSection)
                    ? (bool) $submittedSection['is_enabled']
                    : (bool) ($baselineSection['is_enabled'] ?? true),
                'payload' => array_replace(
                    $baselineSection['payload'] ?? [],
                    $submittedSection['payload'] ?? []
                ),
            ];
        }

        return [
            'page' => $page,
            'sections' => $sections,
        ];
    }

    private function stageSnapshot(
        array $snapshot,
        SiteChromeService $chrome,
        PageWorkflowService $workflow
    ): void {
        $this->page = $workflow->stage(
            $this->page,
            $snapshot,
            $this->originalLockVersion,
            auth()->user()
        );
        $this->loadEditorState($workflow);
        activity('cms')->causedBy(auth()->user())->performedOn($this->page)->log('saved page draft');
        $chrome->forget();
    }

    private function normalizedDraftSnapshot(): array
    {
        $snapshot = $this->page->draft_snapshot ?? [];
        if (data_get($snapshot, 'page.is_indexable') === null) {
            data_set($snapshot, 'page.is_indexable', (bool) $this->page->is_indexable);
        }

        return $snapshot;
    }

    public function restoreRevision(
        int $revisionId,
        SiteChromeService $chrome,
        PageWorkflowService $workflow
    ): void {
        $this->authorizeAccess();
        abort_unless(auth()->user()->can('publish content'), 403);
        $revision = ContentRevision::query()->findOrFail($revisionId);
        $pageType = $this->page->getMorphClass();
        $sectionType = (new PageSection)->getMorphClass();

        $snapshot = $this->page->draft_snapshot ?: $workflow->snapshot($this->page);
        if ($revision->revisionable_type === $pageType && (int) $revision->revisionable_id === $this->page->id) {
            if (isset($revision->snapshot['page'], $revision->snapshot['sections'])) {
                $snapshot = $revision->snapshot;
            } else {
                $snapshot['page'] = array_replace(
                    $snapshot['page'],
                    collect($revision->snapshot)->only(array_keys($snapshot['page']))->all()
                );
            }
        } elseif ($revision->revisionable_type === $sectionType) {
            $section = $this->page->sections->firstWhere('id', (int) $revision->revisionable_id);
            abort_unless($section, 422);
            $snapshot['sections'][$section->id] = [
                'is_enabled' => (bool) ($revision->snapshot['is_enabled'] ?? true),
                'payload' => $revision->snapshot['payload'] ?? [],
            ];
        } else {
            abort(422);
        }

        $this->page = $workflow->publishDirect(
            $this->page,
            $snapshot,
            $this->originalLockVersion,
            auth()->user()
        );
        activity('cms')
            ->causedBy(auth()->user())
            ->performedOn($this->page)
            ->withProperties(['revision_id' => $revision->id])
            ->log('restored content revision');

        $this->loadEditorState($workflow);
        $chrome->forget();
        $this->cachePreview();
        session()->flash('success', "Revision {$revision->version} was restored and published.");
    }

    public function selectPageMedia(?int $assetId): void
    {
        $this->authorizeAccess();
        abort_unless($this->canEditPageSettings(), 403);
        $this->assertMediaAsset($assetId, ['image', 'icon']);
        $this->pageForm['og_media_id'] = $assetId;
        $this->refreshUnsavedPreview();
    }

    public function uploadPageMedia(): void
    {
        $this->authorizeAccess();
        abort_unless($this->canEditPageSettings(), 403);

        $asset = $this->storeUploadedMedia(
            'page-og_media_id',
            'image',
            'Social image',
            app(AdminMediaUploadService::class)
        );
        $this->pageForm['og_media_id'] = $asset->id;
        unset($this->mediaUploads['page-og_media_id']);
        $this->refreshUnsavedPreview();
        session()->flash('success', 'Image uploaded and selected.');
    }

    public function selectSectionMedia(int $sectionId, string $fieldId, ?int $assetId): void
    {
        $this->authorizeAccess();
        $field = $this->sectionField($sectionId, $fieldId);
        abort_unless($field && $this->canEditField($sectionId, $fieldId, $field), 403);
        $this->assertMediaAsset($assetId, [($field['input'] ?? '') === 'icon' ? 'icon' : 'image']);
        $this->sections[$sectionId]['payload'][$fieldId] = $assetId;
        $this->refreshUnsavedPreview();
    }

    public function uploadSectionMedia(int $sectionId, string $fieldId): void
    {
        $this->authorizeAccess();
        $field = $this->sectionField($sectionId, $fieldId);
        abort_unless($field && $this->canEditField($sectionId, $fieldId, $field), 403);

        $kind = ($field['input'] ?? '') === 'icon' ? 'icon' : 'image';
        $asset = $this->storeUploadedMedia(
            $this->sectionUploadKey($sectionId, $fieldId),
            $kind,
            $field['editor_label'] ?? $field['label'] ?? 'Page image',
            app(AdminMediaUploadService::class)
        );
        $this->sections[$sectionId]['payload'][$fieldId] = $asset->id;
        unset($this->mediaUploads[$this->sectionUploadKey($sectionId, $fieldId)]);
        $this->refreshUnsavedPreview();
        session()->flash('success', 'Media uploaded and selected.');
    }

    private function assertMediaAsset(?int $assetId, array $allowedKinds): void
    {
        if (! $assetId) {
            return;
        }

        abort_unless(
            MediaAsset::query()->whereKey($assetId)->whereIn('kind', $allowedKinds)->exists(),
            422,
            'Select a valid media asset.'
        );
    }

    private function sectionField(int $sectionId, string $fieldId): ?array
    {
        $section = $this->page->sections->firstWhere('id', $sectionId);

        return $section->field_schema['fields'][$fieldId] ?? null;
    }

    public function sectionUploadKey(int $sectionId, string $fieldId): string
    {
        return "section-{$sectionId}-{$fieldId}";
    }

    public function acceptedMediaTypes(string $input): string
    {
        return $input === 'icon'
            ? '.jpg,.jpeg,.png,.webp,.gif,.svg'
            : '.jpg,.jpeg,.png,.webp,.gif';
    }

    private function storeUploadedMedia(
        string $uploadKey,
        string $kind,
        string $label,
        AdminMediaUploadService $uploader
    ): MediaAsset
    {
        $upload = $this->mediaUploads[$uploadKey] ?? null;
        $validator = Validator::make(
            ['upload' => $upload],
            ['upload' => ['required', 'file', 'max:'.config('cms.max_upload_kilobytes')]]
        );
        if ($validator->fails()) {
            throw ValidationException::withMessages([
                "mediaUploads.{$uploadKey}" => $validator->errors()->first('upload'),
            ]);
        }

        try {
            return $uploader->store($upload, $kind, $label);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages([
                "mediaUploads.{$uploadKey}" => $exception->errors()['upload'][0] ?? 'The image could not be uploaded.',
            ]);
        }
    }

    public function render()
    {
        $this->authorizeAccess();
        $sectionIds = $this->page->sections->modelKeys();
        $sectionType = (new PageSection)->getMorphClass();
        $revisions = ContentRevision::query()
            ->with('user')
            ->where(function ($query) use ($sectionIds, $sectionType): void {
                $query->where(function ($query): void {
                    $query->where('revisionable_type', $this->page->getMorphClass())
                        ->where('revisionable_id', $this->page->id);
                });
                if ($sectionIds !== []) {
                    $query->orWhere(function ($query) use ($sectionIds, $sectionType): void {
                        $query->where('revisionable_type', $sectionType)
                            ->whereIn('revisionable_id', $sectionIds);
                    });
                }
            })
            ->latest()
            ->limit(40)
            ->get();
        $sectionLabels = $this->page->sections->pluck('label', 'id');

        return view('livewire.admin.page-editor', [
            'media' => MediaAsset::query()->with('media')->orderBy('title')->get(),
            'previewUrl' => URL::temporarySignedRoute(
                'admin.pages.preview',
                now()->addMinutes(30),
                ['page' => $this->page, 'editor' => $this->previewToken]
            ),
            'revisions' => $revisions,
            'sectionLabels' => $sectionLabels,
        ])->title('Edit '.$this->page->title)->layoutData(['heading' => 'Edit '.$this->page->title]);
    }

    private function authorizeAccess(): void
    {
        $user = auth()->user();
        abort_unless($user?->can('access admin'), 403);
        abort_unless(
            $user->can('manage pages') || $this->gate->hasAnyGrantForTemplate($user, $this->page->template_key),
            403
        );
    }

    public function canEditField(int $sectionId, string $fieldId, array $field): bool
    {
        $section = $this->page->sections->firstWhere('id', $sectionId);
        if (! $section) {
            return false;
        }

        $fieldGroup = $this->classifier->classify($field, $section->section_key);

        return $this->gate->allows(auth()->user(), $this->page->template_key, $section->section_key, $fieldGroup);
    }

    public function canEditSection(int $sectionId): bool
    {
        $section = $this->page->sections->firstWhere('id', $sectionId);
        if (! $section) {
            return false;
        }

        return $this->gate->allowsSection(auth()->user(), $this->page->template_key, $section->section_key);
    }

    public function canEditPageSettings(): bool
    {
        return $this->gate->isUnrestricted(auth()->user());
    }

    public function editableFieldsFor(int $sectionId): \Illuminate\Support\Collection
    {
        $section = $this->page->sections->firstWhere('id', $sectionId);
        if (! $section) {
            return collect();
        }

        return collect($this->editorSchema->fields($this->page->template_key, $section))
            ->filter(fn (array $field, string $fieldId): bool => $this->canEditField(
                $section->id,
                $fieldId,
                $field
            ));
    }

    public function manageUrl(string $sectionKey): ?string
    {
        $resource = match (true) {
            in_array($sectionKey, ['news'], true) => 'posts',
            in_array($sectionKey, ['players', 'coaches', 'leadership'], true) => 'people',
            str_contains($sectionKey, 'schedule'), $sectionKey === 'next_game' => 'games',
            str_contains($sectionKey, 'partner') => 'sponsors',
            str_contains($sectionKey, 'standing') => 'standings',
            default => null,
        };

        if (! $resource || ! array_key_exists($resource, app(\App\Domain\Admin\ResourceRegistry::class)->all())) {
            return null;
        }

        return route('admin.resources', $resource);
    }

    private function cachePreview(): void
    {
        if ($this->previewToken === '') {
            return;
        }

        Cache::put(
            "cms-page-preview:".auth()->id().":{$this->page->id}:{$this->previewToken}",
            ['page' => $this->pageForm, 'sections' => $this->sections],
            now()->addMinutes(30)
        );
    }

    private function refreshUnsavedPreview(): void
    {
        $this->hasUnsavedChanges = true;
        $this->cachePreview();
        $this->dispatch('cms-preview-refresh');
    }
}
