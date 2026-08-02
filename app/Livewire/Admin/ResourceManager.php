<?php

namespace App\Livewire\Admin;

use App\Domain\Admin\ResourceRegistry;
use App\Enums\PublicationStatus;
use App\Models\Category;
use App\Models\ContentRevision;
use App\Models\Game;
use App\Models\MediaAsset;
use App\Models\NavigationItem;
use App\Models\Person;
use App\Models\Post;
use App\Models\RosterMembership;
use App\Models\Season;
use App\Models\Sponsor;
use App\Models\SponsorTier;
use App\Models\StaffAssignment;
use App\Models\Standing;
use App\Models\Team;
use App\Models\Venue;
use App\Rules\SafeUrl;
use App\Services\ResourceWorkflowService;
use App\Services\AdminMediaUploadService;
use App\Services\SiteChromeService;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
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
class ResourceManager extends Component
{
    use WithFileUploads, WithPagination;

    #[Locked]
    public string $resource;

    public string $search = '';

    public string $statusFilter = '';

    public string $sortField = '';

    public string $sortDirection = 'asc';

    public int $perPage = 15;

    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public ?int $originalLockVersion = null;

    public array $form = [];

    public array $selected = [];

    public array $mediaUploads = [];

    public array $repeaters = [];

    public bool $slugManuallyEdited = false;

    public bool $showEditor = false;

    public function mount(string $resource, ResourceRegistry $registry): void
    {
        $this->resource = $resource;
        $config = $this->authorizedConfig($registry);
        $this->sortField = $config['sort'];
        $this->newRecord($registry);
        $this->showEditor = false;
    }

    public function updatedSearch(): void
    {
        $this->ensureResourceAccess(app(ResourceRegistry::class));
        $this->selected = [];
        $this->resetValidation('selected');
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->ensureResourceAccess(app(ResourceRegistry::class));
        $this->selected = [];
        $this->resetValidation('selected');
        $this->resetPage();
    }

    public function updatedSelected(): void
    {
        $this->ensureResourceAccess(app(ResourceRegistry::class));
        $this->resetValidation('selected');
    }

    public function updatedPerPage(): void
    {
        $this->ensureResourceAccess(app(ResourceRegistry::class));
        if (! in_array($this->perPage, [10, 15, 25, 50], true)) {
            $this->perPage = 15;
        }
        $this->resetPage();
    }

    public function sortBy(string $field, ResourceRegistry $registry): void
    {
        $config = $this->authorizedConfig($registry);
        abort_unless(array_key_exists($field, $config['columns']) && ! str_contains($field, '.'), 422);

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->ensureResourceAccess(app(ResourceRegistry::class));
        $this->reset(['search', 'statusFilter']);
        $this->selected = [];
        $this->resetValidation('selected');
        $this->resetPage();
    }

    public function togglePageSelection(string $ids): void
    {
        $this->ensureResourceAccess(app(ResourceRegistry::class));
        $pageIds = collect(explode(',', $ids))
            ->filter(fn (string $id) => ctype_digit($id))
            ->map(fn (string $id) => (int) $id)
            ->values();
        $selected = collect($this->selected)->map(fn ($id) => (int) $id);

        if ($pageIds->isNotEmpty() && $pageIds->every(fn (int $id) => $selected->contains($id))) {
            $this->selected = $selected->diff($pageIds)->values()->all();
        } else {
            $this->selected = $selected->merge($pageIds)->unique()->values()->all();
        }

        $this->resetValidation('selected');
    }

    public function clearSelection(): void
    {
        $this->ensureResourceAccess(app(ResourceRegistry::class));
        $this->selected = [];
        $this->resetValidation('selected');
    }

    public function newRecord(ResourceRegistry $registry): void
    {
        $config = $this->authorizedConfig($registry);
        $this->editingId = null;
        $this->originalLockVersion = null;
        $this->form = [];
        $this->repeaters = [];
        $this->slugManuallyEdited = false;

        foreach ($config['fields'] as $key => $field) {
            $this->form[$key] = match ($field['type']) {
                'checkbox' => false,
                'json' => '',
                default => in_array($key, ['status', 'publication_status'], true) ? 'published' : '',
            };
            if ($field['type'] === 'json') {
                $this->repeaters[$key] = [];
            }
        }

        if (array_key_exists('timezone', $config['fields'])) {
            $this->form['timezone'] = 'America/New_York';
        }
        $this->showEditor = true;
        $this->resetValidation();
    }

    public function edit(int $id, ResourceRegistry $registry): void
    {
        $config = $this->authorizedConfig($registry);
        /** @var Model $model */
        $model = $config['model']::query()->findOrFail($id);
        $this->editingId = $id;
        $this->originalLockVersion = (int) $model->getAttribute(
            ($config['workflow'] ?? false) ? 'draft_lock_version' : 'lock_version'
        );
        $this->form = [];
        $this->repeaters = [];
        $this->slugManuallyEdited = true;
        $snapshot = ($config['workflow'] ?? false)
            ? ($model->getAttribute('draft_snapshot') ?: [])
            : [];

        foreach ($config['fields'] as $key => $field) {
            $value = array_key_exists($key, $snapshot)
                ? $snapshot[$key]
                : $model->getAttribute($key);
            if (($config['workflow'] ?? false) && $key === $config['status_field']) {
                $value = $model->getAttribute('workflow_status');
            }
            if ($value instanceof BackedEnum) {
                $value = $value->value;
            } elseif ($value instanceof Carbon) {
                $value = $field['type'] === 'date'
                    ? $value->format('Y-m-d')
                    : $value->format('Y-m-d\TH:i');
            } elseif ($field['type'] === 'json') {
                $this->repeaters[$key] = $this->repeaterRows($value);
                $value = $value ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '';
            }
            $this->form[$key] = $value ?? ($field['type'] === 'checkbox' ? false : '');
        }

        $this->showEditor = true;
        $this->resetValidation();
    }

    public function save(
        ResourceRegistry $registry,
        ResourceWorkflowService $workflow,
        SiteChromeService $chrome
    ): void {
        $config = $this->authorizedConfig($registry);
        $this->syncRepeaters($config);
        $validated = $this->validate($this->validationRules($config))['form'];
        $statusField = $config['status_field'] ?? null;

        foreach ($validated as $key => $value) {
            if (($key === 'url' || str_ends_with($key, '_url')) && ! SafeUrl::allows($value)) {
                throw ValidationException::withMessages([
                    'form.'.$key => 'This URL is not allowed.',
                ]);
            }
        }

        if (
            $statusField
            && ($validated[$statusField] ?? null) === PublicationStatus::Scheduled->value
            && (
                ! filled($validated['publish_at'] ?? null)
                || ! Carbon::parse($validated['publish_at'])->isFuture()
            )
        ) {
            throw ValidationException::withMessages([
                'form.publish_at' => 'Choose a future publication time.',
            ]);
        }

        foreach ($config['fields'] as $key => $field) {
            if ($field['type'] === 'json') {
                $validated[$key] = filled($validated[$key] ?? null)
                    ? json_decode($validated[$key], true, 512, JSON_THROW_ON_ERROR)
                    : null;
            }
            if ($field['type'] === 'checkbox') {
                $validated[$key] = (bool) ($validated[$key] ?? false);
            }
            if (($validated[$key] ?? null) === '') {
                $validated[$key] = null;
            }
        }

        if (isset($validated['slug']) && ! $validated['slug']) {
            $validated['slug'] = Str::slug($validated['title'] ?? $validated['name'] ?? Str::uuid());
        }

        $this->validateDomainRules($validated);

        if ($config['workflow'] ?? false) {
            $this->saveWorkflowResource($config, $validated, $workflow);
            $chrome->forget();
            $this->showEditor = false;

            return;
        }

        DB::transaction(function () use ($config, $validated): void {
            /** @var Model $model */
            $model = $this->editingId
                ? $config['model']::query()->lockForUpdate()->findOrFail($this->editingId)
                : new $config['model'];

            if (
                $this->editingId
                && $this->originalLockVersion !== null
                && (int) $model->getAttribute('lock_version') !== $this->originalLockVersion
            ) {
                throw ValidationException::withMessages([
                    'form' => 'This record changed in another session. Reload it before saving.',
                ]);
            }

            if (array_key_exists('lock_version', $model->getAttributes())) {
                $validated['lock_version'] = ((int) $model->getAttribute('lock_version')) + 1;
            }

            $model->fill($validated)->save();
            $this->editingId = (int) $model->getKey();
            $this->originalLockVersion = $model->getAttribute('lock_version');

            activity('cms')
                ->causedBy(auth()->user())
                ->performedOn($model)
                ->withProperties(['resource' => $this->resource])
                ->log($model->wasRecentlyCreated ? 'created' : 'updated');
        });

        $chrome->forget();
        session()->flash('success', 'The record was saved.');
        $this->showEditor = false;
    }

    public function updatedForm(mixed $value, string $key): void
    {
        if ($key === 'slug') {
            $this->slugManuallyEdited = true;

            return;
        }
        if (
            in_array($key, ['name', 'title', 'display_name'], true)
            && array_key_exists('slug', $this->form)
            && ! $this->slugManuallyEdited
        ) {
            $this->form['slug'] = Str::slug((string) $value);
        }
    }

    public function addRepeaterItem(string $fieldKey, ResourceRegistry $registry): void
    {
        $field = $this->authorizedConfig($registry)['fields'][$fieldKey] ?? null;
        abort_unless(($field['type'] ?? null) === 'json', 422);
        $this->repeaters[$fieldKey][] = ['key' => '', 'value' => ''];
    }

    public function removeRepeaterItem(string $fieldKey, int $index, ResourceRegistry $registry): void
    {
        $field = $this->authorizedConfig($registry)['fields'][$fieldKey] ?? null;
        abort_unless(($field['type'] ?? null) === 'json', 422);
        unset($this->repeaters[$fieldKey][$index]);
        $this->repeaters[$fieldKey] = array_values($this->repeaters[$fieldKey]);
    }

    public function selectMedia(string $fieldKey, ?int $assetId, ResourceRegistry $registry): void
    {
        $config = $this->authorizedConfig($registry);
        $field = $config['fields'][$fieldKey] ?? null;
        abort_unless($field && in_array($field['options'], ['media_images', 'media_icons'], true), 422);
        if ($assetId) {
            $allowedKinds = $field['options'] === 'media_icons' ? ['icon'] : ['image', 'icon'];
            abort_unless(MediaAsset::query()->whereKey($assetId)->whereIn('kind', $allowedKinds)->exists(), 422);
        }
        $this->form[$fieldKey] = $assetId;
    }

    public function uploadMedia(
        string $fieldKey,
        ResourceRegistry $registry,
        AdminMediaUploadService $uploader
    ): void {
        $config = $this->authorizedConfig($registry);
        $field = $config['fields'][$fieldKey] ?? null;
        abort_unless($field && in_array($field['options'], ['media_images', 'media_icons'], true), 422);
        $kind = $field['options'] === 'media_icons' ? 'icon' : 'image';
        $key = "resource-{$fieldKey}";
        $upload = $this->mediaUploads[$key] ?? null;
        if (! $upload) {
            $this->addError("mediaUploads.{$key}", 'Choose a file first.');

            return;
        }

        try {
            $asset = $uploader->store($upload, $kind, $field['label']);
        } catch (ValidationException $exception) {
            $this->addError("mediaUploads.{$key}", $exception->errors()['upload'][0] ?? 'Upload failed.');

            return;
        }
        $this->form[$fieldKey] = $asset->id;
        unset($this->mediaUploads[$key]);
    }

    public function delete(
        int $id,
        ResourceRegistry $registry,
        ?SiteChromeService $chrome = null
    ): void {
        $config = $this->authorizedConfig($registry);
        $model = $config['model']::query()->findOrFail($id);

        $this->validateSoftDeletion($model);
        $model->delete();

        activity('cms')->causedBy(auth()->user())->performedOn($model)->log('moved to trash');
        $this->selected = array_values(array_diff($this->selected, [$id]));
        ($chrome ?? app(SiteChromeService::class))->forget();
        session()->flash('success', 'The record was moved to Trash.');
    }

    public function restore(
        int $id,
        ResourceRegistry $registry,
        ?SiteChromeService $chrome = null
    ): void {
        $config = $this->authorizedConfig($registry);
        /** @var Model $model */
        $model = $config['model']::onlyTrashed()->findOrFail($id);
        $model->restore();
        if (method_exists($model, 'recordRevision')) {
            $model->recordRevision('restored');
        }
        activity('cms')->causedBy(auth()->user())->performedOn($model)->log('restored from trash');
        $this->selected = array_values(array_diff($this->selected, [$id]));
        ($chrome ?? app(SiteChromeService::class))->forget();
        session()->flash('success', 'The record was restored.');
    }

    public function restoreRevision(
        int $revisionId,
        ResourceRegistry $registry,
        ResourceWorkflowService $workflow,
        SiteChromeService $chrome
    ): void {
        $config = $this->authorizedConfig($registry);
        abort_unless($this->editingId, 422);

        /** @var Model $model */
        $model = $config['model']::query()->findOrFail($this->editingId);
        abort_unless(method_exists($model, 'restoreRevision'), 422);

        $revision = ContentRevision::query()
            ->where('revisionable_type', $model->getMorphClass())
            ->where('revisionable_id', $model->getKey())
            ->findOrFail($revisionId);

        if ($config['workflow'] ?? false) {
            $snapshot = collect($revision->snapshot)
                ->only(array_keys($config['fields']))
                ->except([$config['status_field'], 'publish_at'])
                ->all();
            abort_if($snapshot === [], 422, 'This revision does not contain restorable content.');
            $model = $workflow->stage(
                $model,
                $snapshot,
                (int) $model->draft_lock_version,
                auth()->user(),
                'draft_restored'
            );
        } else {
            DB::transaction(fn () => $model->restoreRevision($revision));
        }
        $this->edit((int) $model->getKey(), $registry);
        $chrome->forget();
        session()->flash('success', "Revision {$revision->version} was restored.");
    }

    public function bulkPublish(
        ResourceRegistry $registry,
        ResourceWorkflowService $workflow,
        SiteChromeService $chrome
    ): void {
        $config = $this->authorizedConfig($registry);
        $statusField = $config['status_field'] ?? null;
        abort_unless($statusField, 422);

        $selectedIds = collect($this->selected)->map(fn ($id) => (int) $id)->unique()->values();
        if ($selectedIds->isEmpty()) {
            throw ValidationException::withMessages([
                'selected' => 'Select at least one draft record.',
            ]);
        }

        $models = $config['model']::query()->whereKey($selectedIds)->get();
        $workflowStatus = ($config['workflow'] ?? false) ? 'workflow_status' : $statusField;
        $eligible = $models->filter(function (Model $model) use ($workflowStatus): bool {
            $status = $model->getAttribute($workflowStatus);

            return ($status instanceof BackedEnum ? $status->value : $status)
                === PublicationStatus::Draft->value;
        });

        if ($models->count() !== $selectedIds->count() || $eligible->count() !== $models->count()) {
            throw ValidationException::withMessages([
                'selected' => 'Only existing draft records can be published in bulk.',
            ]);
        }

        DB::transaction(function () use ($eligible, $statusField, $workflow, $config): void {
            $eligible->each(function (Model $model) use ($statusField, $workflow, $config): void {
                if ($config['workflow'] ?? false) {
                    $this->validatePublishableRelations($model);
                    if (! $model->draft_snapshot) {
                        $snapshot = $workflow->snapshot(
                            $model,
                            array_values(array_diff(
                                array_keys($config['fields']),
                                [$statusField, 'publish_at']
                            ))
                        );
                        $model = $workflow->stage(
                            $model,
                            $snapshot,
                            (int) $model->draft_lock_version,
                            auth()->user(),
                            'legacy_draft_migrated'
                        );
                    }
                    $workflow->publish($model, $statusField, auth()->user());

                    return;
                }

                $model->forceFill([$statusField => PublicationStatus::Published->value])->save();
            });
        });

        $count = $eligible->count();
        $this->selected = [];
        $chrome->forget();
        session()->flash('success', "{$count} ".str('record')->plural($count).' published.');
    }

    public function export(ResourceRegistry $registry)
    {
        $config = $this->authorizedConfig($registry);
        $rows = $this->query($config)->get();
        $columns = $config['columns'];

        return response()->streamDownload(function () use ($rows, $columns): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, array_values($columns));
            foreach ($rows as $row) {
                fputcsv($output, collect(array_keys($columns))->map(
                    fn ($key) => $this->csvValue(data_get($row, $key))
                )->all());
            }
            fclose($output);
        }, $this->resource.'-'.now()->format('Y-m-d').'.csv');
    }

    public function render(ResourceRegistry $registry)
    {
        $config = $this->authorizedConfig($registry);
        $rows = $this->query($config)->paginate($this->perPage);
        $revisions = collect();
        if ($this->editingId) {
            $model = $config['model']::query()->find($this->editingId);
            if ($model && method_exists($model, 'revisions')) {
                $revisions = $model->revisions()->with('user')->limit(10)->get();
            }
        }
        $options = collect($config['fields'])
            ->filter(fn ($field) => $field['options'])
            ->mapWithKeys(fn ($field) => [$field['options'] => $registry->options($field['options'])])
            ->all();
        $statusOptions = isset($config['status_field'])
            ? $registry->options('publication_statuses')
            : [];
        if (isset($options['publication_statuses']) && ! array_key_exists('publish_at', $config['fields'])) {
            unset($options['publication_statuses'][PublicationStatus::Scheduled->value]);
        }
        $media = MediaAsset::query()->with('media')->orderBy('title')->get();

        return view('livewire.admin.resource-manager', compact(
            'config',
            'rows',
            'options',
            'revisions',
            'statusOptions',
            'media'
        ))
            ->title($config['label'])
            ->layoutData(['heading' => $config['label']]);
    }

    private function query(array $config)
    {
        $query = $config['model']::query()->with($config['with'] ?? []);
        if ($this->statusFilter === 'trashed') {
            $query->onlyTrashed();
        }
        if ($this->search !== '') {
            $query->where(function ($query) use ($config): void {
                foreach ($config['search'] as $index => $column) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $query->{$method}($column, 'like', '%'.$this->search.'%');
                }
            });
        }
        if (
            $this->statusFilter !== ''
            && $this->statusFilter !== 'trashed'
            && isset($config['status_field'])
        ) {
            $query->where(
                ($config['workflow'] ?? false) ? 'workflow_status' : $config['status_field'],
                $this->statusFilter
            );
        }

        $sort = array_key_exists($this->sortField, $config['columns']) && ! str_contains($this->sortField, '.')
            ? $this->sortField
            : $config['sort'];

        $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sort, $direction);
    }

    public function displayValue(mixed $value): string
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('M j, Y g:i A');
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        return (string) ($value ?? '');
    }

    private function csvValue(mixed $value): string
    {
        $formatted = $this->displayValue($value);

        return preg_match('/^[=+\-@]/', $formatted) ? "'".$formatted : $formatted;
    }

    private function authorizedConfig(ResourceRegistry $registry): array
    {
        $config = $registry->get($this->resource);
        abort_unless(auth()->user()?->can($config['permission']), 403);

        return $config;
    }

    private function ensureResourceAccess(ResourceRegistry $registry): void
    {
        $this->authorizedConfig($registry);
    }

    private function validationRules(array $config): array
    {
        $rules = collect($config['fields'])->mapWithKeys(
            fn ($field, $key) => ['form.'.$key => explode('|', $field['rules'])]
        )->all();
        $table = (new $config['model'])->getTable();

        foreach (['slug', 'source_path'] as $uniqueField) {
            if (isset($config['fields'][$uniqueField])) {
                $rules['form.'.$uniqueField][] = Rule::unique($table, $uniqueField)
                    ->ignore($this->editingId);
            }
        }

        if ($this->resource === 'roster-memberships') {
            $rules['form.person_id'][] = Rule::unique('roster_memberships', 'person_id')
                ->where('season_id', $this->form['season_id'] ?? null)
                ->ignore($this->editingId);
        }
        if ($this->resource === 'staff-assignments') {
            $rules['form.person_id'][] = Rule::unique('staff_assignments', 'person_id')
                ->where(function ($query): void {
                    filled($this->form['season_id'] ?? null)
                        ? $query->where('season_id', $this->form['season_id'])
                        : $query->whereNull('season_id');
                    $query->where('role', $this->form['role'] ?? '');
                })
                ->ignore($this->editingId);
        }
        if ($this->resource === 'standings') {
            $rules['form.team_id'][] = Rule::unique('standings', 'team_id')
                ->where(function ($query): void {
                    $query->where('season_id', $this->form['season_id'] ?? null);
                    filled($this->form['division'] ?? null)
                        ? $query->where('division', $this->form['division'])
                        : $query->whereNull('division');
                })
                ->ignore($this->editingId);
        }

        return $rules;
    }

    private function validateDomainRules(array $validated): void
    {
        if ($this->resource !== 'games') {
            return;
        }

        if (
            ($validated['status'] ?? null) === 'final'
            && (
                ($validated['home_score'] ?? null) === null
                || ($validated['away_score'] ?? null) === null
            )
        ) {
            throw ValidationException::withMessages([
                'form.home_score' => 'Both scores are required for a final game.',
                'form.away_score' => 'Both scores are required for a final game.',
            ]);
        }

        $teams = Team::query()
            ->whereKey([$validated['home_team_id'], $validated['away_team_id']])
            ->get();
        if ($teams->where('is_home_team', true)->count() !== 1) {
            throw ValidationException::withMessages([
                'form.home_team_id' => 'Each game must include the DMV Warriors exactly once.',
                'form.away_team_id' => 'Each game must include the DMV Warriors exactly once.',
            ]);
        }
    }

    private function repeaterRows(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        if (array_is_list($value)) {
            return collect($value)->map(fn ($item) => [
                'key' => '',
                'value' => is_scalar($item) ? (string) $item : json_encode($item, JSON_UNESCAPED_SLASHES),
            ])->all();
        }

        return collect($value)->map(fn ($item, $key) => [
            'key' => (string) $key,
            'value' => is_scalar($item) ? (string) $item : json_encode($item, JSON_UNESCAPED_SLASHES),
        ])->values()->all();
    }

    private function syncRepeaters(array $config): void
    {
        foreach ($config['fields'] as $key => $field) {
            if ($field['type'] !== 'json') {
                continue;
            }
            $rows = collect($this->repeaters[$key] ?? [])
                ->filter(fn ($row) => filled($row['key'] ?? null) || filled($row['value'] ?? null));
            $hasKeys = $rows->contains(fn ($row) => filled($row['key'] ?? null));
            $value = $hasKeys
                ? $rows->mapWithKeys(fn ($row) => [(string) $row['key'] => (string) ($row['value'] ?? '')])->all()
                : $rows->pluck('value')->values()->all();
            $this->form[$key] = $value === [] ? '' : json_encode($value, JSON_UNESCAPED_SLASHES);
        }
    }

    private function saveWorkflowResource(
        array $config,
        array $validated,
        ResourceWorkflowService $workflow
    ): void {
        $statusField = $config['status_field'];
        $requestedStatus = $validated[$statusField];
        $publishAt = $requestedStatus === PublicationStatus::Scheduled->value
            && filled($validated['publish_at'] ?? null)
            ? Carbon::parse($validated['publish_at'])
            : null;
        $snapshot = collect($validated)
            ->except([$statusField, 'publish_at'])
            ->all();
        /** @var Model|null $model */
        $model = $this->editingId
            ? $config['model']::query()->findOrFail($this->editingId)
            : null;
        $created = false;

        if (! $model) {
            $model = $workflow->create(new $config['model'], $snapshot, $statusField, auth()->user());
            $created = true;
            $this->editingId = (int) $model->getKey();
            $this->originalLockVersion = (int) $model->draft_lock_version;
        }

        if ($requestedStatus === PublicationStatus::Draft->value) {
            if (! $created) {
                $model = $workflow->stage(
                    $model,
                    $snapshot,
                    (int) $this->originalLockVersion,
                    auth()->user()
                );
            }
            $message = 'Draft changes were saved.';
        } elseif (in_array($requestedStatus, [
            PublicationStatus::Published->value,
            PublicationStatus::Scheduled->value,
        ], true)) {
            $currentWorkflow = $model->workflow_status instanceof BackedEnum
                ? $model->workflow_status->value
                : $model->workflow_status;
            if (
                ! $created
                && (
                    $currentWorkflow !== PublicationStatus::Draft->value
                    || $snapshot != $model->draft_snapshot
                )
            ) {
                $model = $workflow->stage(
                    $model,
                    $snapshot,
                    (int) $this->originalLockVersion,
                    auth()->user()
                );
            }
            $this->validatePublishableRelations($model);
            if ($requestedStatus === PublicationStatus::Scheduled->value) {
                $model = $workflow->schedule(
                    $model,
                    auth()->user(),
                    $publishAt
                );
                $message = 'The record was scheduled.';
            } else {
                $model = $workflow->publish($model, $statusField, auth()->user());
                $message = 'The record was published.';
            }
        } elseif ($requestedStatus === PublicationStatus::Archived->value) {
            $model = $workflow->archive($model, $statusField, auth()->user());
            $message = 'The record was archived.';
        }

        $this->originalLockVersion = (int) $model->draft_lock_version;
        activity('cms')
            ->causedBy(auth()->user())
            ->performedOn($model)
            ->withProperties(['resource' => $this->resource])
            ->log($message);
        session()->flash('success', $message);
    }

    private function validateSoftDeletion(Model $model): void
    {
        if ($model instanceof Season) {
            abort_if($model->is_current, 422, 'Set another season as current before moving this one to Trash.');
            $model->loadCount(['games', 'rosterMemberships', 'staffAssignments', 'standings']);
            abort_if(
                $model->games_count
                    + $model->roster_memberships_count
                    + $model->staff_assignments_count
                    + $model->standings_count > 0,
                422,
                'This season is still referenced. Move or remove its schedule and roster records first.'
            );
        }

        if ($model instanceof Team) {
            abort_if($model->is_home_team, 422, 'Set another team as the DMV Warriors team before moving this one to Trash.');
            abort_if(
                Game::query()
                    ->where(fn ($query) => $query
                        ->where('home_team_id', $model->id)
                        ->orWhere('away_team_id', $model->id))
                    ->exists()
                    || Standing::query()->where('team_id', $model->id)->exists(),
                422,
                'This team is still referenced by games or standings.'
            );
        }

        $referenced = match (true) {
            $model instanceof Person => RosterMembership::query()
                ->where('person_id', $model->id)->exists()
                || StaffAssignment::query()->where('person_id', $model->id)->exists(),
            $model instanceof Venue => Game::query()->where('venue_id', $model->id)->exists(),
            $model instanceof Category => Post::query()->where('category_id', $model->id)->exists(),
            $model instanceof SponsorTier => Sponsor::query()
                ->where('sponsor_tier_id', $model->id)->exists(),
            $model instanceof NavigationItem => NavigationItem::query()
                ->where('parent_id', $model->id)->exists(),
            default => false,
        };

        abort_if($referenced, 422, 'This record is still referenced by other content.');
    }

    private function validatePublishableRelations(Model $model): void
    {
        if (! $model instanceof Game) {
            return;
        }

        $snapshot = $model->draft_snapshot ?? [];
        $season = Season::query()->find($snapshot['season_id'] ?? null);
        $teams = Team::query()
            ->whereKey([$snapshot['home_team_id'] ?? null, $snapshot['away_team_id'] ?? null])
            ->get();
        $venue = filled($snapshot['venue_id'] ?? null)
            ? Venue::query()->find($snapshot['venue_id'])
            : null;
        $unpublished = ! $season
            || $season->status !== PublicationStatus::Published
            || $teams->count() !== 2
            || $teams->contains(fn (Team $team) => $team->status !== PublicationStatus::Published)
            || (filled($snapshot['venue_id'] ?? null)
                && (! $venue || $venue->status !== PublicationStatus::Published));

        if ($unpublished) {
            throw ValidationException::withMessages([
                'form.publication_status' => 'Publish the selected season, teams, and venue before publishing this game.',
            ]);
        }
    }
}
