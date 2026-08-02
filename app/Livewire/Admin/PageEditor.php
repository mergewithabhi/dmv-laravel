<?php

namespace App\Livewire\Admin;

use App\Domain\Content\FieldGroupClassifier;
use App\Models\ContentRevision;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\PageSection;
use App\Rules\SafeUrl;
use App\Services\ContentPermissionGate;
use App\Services\PageWorkflowService;
use App\Services\SiteChromeService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.admin')]
class PageEditor extends Component
{
    public Page $page;

    public array $pageForm = [];

    public array $sections = [];

    #[Locked]
    public int $originalLockVersion;

    public ?string $publishAt = null;

    protected ContentPermissionGate $gate;

    protected FieldGroupClassifier $classifier;

    public function boot(ContentPermissionGate $gate, FieldGroupClassifier $classifier): void
    {
        $this->gate = $gate;
        $this->classifier = $classifier;
    }

    public function mount(Page $page, PageWorkflowService $workflow): void
    {
        $this->page = $page->refresh()->load('sections');
        $this->authorizeAccess();
        $this->loadEditorState($workflow);
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
    }

    public function save(SiteChromeService $chrome, PageWorkflowService $workflow): void
    {
        $this->authorizeAccess();
        $this->stageSnapshot($this->validatedSnapshot(), $chrome, $workflow);
        session()->flash('success', 'Draft changes were saved.');
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
        abort_unless(auth()->user()->can('publish content'), 403);

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

        $this->page = $workflow->stage(
            $this->page,
            $snapshot,
            $this->originalLockVersion,
            auth()->user(),
            'draft_restored'
        );
        activity('cms')
            ->causedBy(auth()->user())
            ->performedOn($this->page)
            ->withProperties(['revision_id' => $revision->id])
            ->log('restored content revision');

        $this->loadEditorState($workflow);
        $chrome->forget();
        session()->flash('success', "Revision {$revision->version} was restored.");
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
            'media' => MediaAsset::query()->orderBy('title')->get(),
            'previewUrl' => URL::temporarySignedRoute('admin.pages.preview', now()->addMinutes(30), $this->page),
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
}
