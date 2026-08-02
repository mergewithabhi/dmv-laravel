<?php

namespace App\Livewire\Admin;

use App\Enums\PublicationStatus;
use App\Models\Page;
use App\Services\ContentPermissionGate;
use App\Services\PageWorkflowService;
use App\Services\SiteChromeService;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
class PagesIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $sortField = 'title';

    public string $sortDirection = 'asc';

    public int $perPage = 15;

    public array $selected = [];

    protected ContentPermissionGate $gate;

    public function boot(ContentPermissionGate $gate): void
    {
        $this->gate = $gate;
    }

    public function mount(): void
    {
        $this->authorizeAccess();
    }

    public function updatedSearch(): void
    {
        $this->authorizeAccess();
        $this->selected = [];
        $this->resetValidation('selected');
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->authorizeAccess();
        $this->selected = [];
        $this->resetValidation('selected');
        $this->resetPage();
    }

    public function updatedSelected(): void
    {
        $this->authorizeAccess();
        $this->resetValidation('selected');
    }

    public function updatedPerPage(): void
    {
        $this->authorizeAccess();
        if (! in_array($this->perPage, [10, 15, 25, 50], true)) {
            $this->perPage = 15;
        }
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        $this->authorizeAccess();
        abort_unless(in_array($field, $this->sortableFields(), true), 422);

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
        $this->authorizeAccess();
        $this->reset(['search', 'statusFilter']);
        $this->selected = [];
        $this->resetValidation('selected');
        $this->resetPage();
    }

    public function togglePageSelection(string $ids): void
    {
        $this->authorizeAccess();
        $pageIds = collect(explode(',', $ids))
            ->filter(fn (string $id) => ctype_digit($id))
            ->map(fn (string $id) => (int) $id)
            ->values()
            ->all();
        $selected = collect($this->selected)->map(fn ($id) => (int) $id);

        if ($pageIds !== [] && collect($pageIds)->every(fn (int $id) => $selected->contains($id))) {
            $this->selected = $selected->reject(fn (int $id) => in_array($id, $pageIds, true))->values()->all();
            $this->resetValidation('selected');

            return;
        }

        $this->selected = $selected->merge($pageIds)->unique()->values()->all();
        $this->resetValidation('selected');
    }

    public function clearSelection(): void
    {
        $this->authorizeAccess();
        $this->selected = [];
        $this->resetValidation('selected');
    }

    public function submit(int $id, PageWorkflowService $workflow): void
    {
        $this->authorizeAccess();
        $page = Page::query()->findOrFail($id);
        $this->authorizeTemplateAccess($page);
        $workflow->submit($page, auth()->user());
        session()->flash('success', "{$page->title} was submitted for review.");
    }

    public function publish(
        int $id,
        SiteChromeService $chrome,
        PageWorkflowService $workflow
    ): void {
        $this->authorizeAccess();
        abort_unless(auth()->user()->can('publish content'), 403);
        $page = Page::query()->findOrFail($id);
        $workflow->approve($page, auth()->user());
        $chrome->forget();
        session()->flash('success', "{$page->title} is published.");
    }

    public function bulkSubmit(PageWorkflowService $workflow): void
    {
        $this->authorizeAccess();
        $pages = Page::query()
            ->whereKey($this->selected)
            ->where('workflow_status', PublicationStatus::Draft->value)
            ->whereNotNull('draft_snapshot')
            ->get()
            ->filter(fn (Page $page) => $this->hasTemplateAccess($page));

        if ($pages->isEmpty()) {
            throw ValidationException::withMessages([
                'selected' => 'Select at least one saved draft that is ready for review.',
            ]);
        }

        $pages->each(fn (Page $page) => $workflow->submit($page, auth()->user()));
        $count = $pages->count();
        $this->selected = [];
        session()->flash('success', "{$count} ".str('page')->plural($count).' submitted for review.');
    }

    public function bulkPublish(
        SiteChromeService $chrome,
        PageWorkflowService $workflow
    ): void {
        $this->authorizeAccess();
        abort_unless(auth()->user()->can('publish content'), 403);
        $pages = Page::query()
            ->whereKey($this->selected)
            ->where('workflow_status', PublicationStatus::InReview->value)
            ->whereNotNull('draft_snapshot')
            ->get();

        if ($pages->isEmpty()) {
            throw ValidationException::withMessages([
                'selected' => 'Select at least one page that is awaiting approval.',
            ]);
        }

        $pages->each(fn (Page $page) => $workflow->approve($page, auth()->user()));
        $chrome->forget();
        $count = $pages->count();
        $this->selected = [];
        session()->flash('success', "{$count} ".str('page')->plural($count).' approved and published.');
    }

    public function export()
    {
        $this->authorizeAccess();
        $pages = $this->pagesQuery()->get();

        return response()->streamDownload(function () use ($pages): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Title', 'Public URL', 'Template', 'Sections', 'Workflow status', 'Updated']);
            foreach ($pages as $page) {
                fputcsv($output, [
                    $this->csvValue($page->title),
                    url($page->slug === 'home' ? '/' : '/'.$page->slug),
                    $this->csvValue($page->template_key),
                    $page->sections_count,
                    $page->workflow_status->label(),
                    $page->updated_at->toIso8601String(),
                ]);
            }
            fclose($output);
        }, 'dmv-pages-'.now()->format('Y-m-d').'.csv');
    }

    public function previewUrl(Page $page): string
    {
        $this->authorizeAccess();
        $this->authorizeTemplateAccess($page);

        return URL::temporarySignedRoute('admin.pages.preview', now()->addMinutes(30), $page);
    }

    public function render()
    {
        $this->authorizeAccess();

        return view('livewire.admin.pages-index', [
            'pages' => $this->pagesQuery()->paginate($this->perPage),
            'statuses' => PublicationStatus::cases(),
        ])->title('Pages')->layoutData(['heading' => 'Pages']);
    }

    private function pagesQuery()
    {
        $search = trim($this->search);
        $sortField = in_array($this->sortField, $this->sortableFields(), true)
            ? $this->sortField
            : 'title';
        $sortDirection = $this->sortDirection === 'desc' ? 'desc' : 'asc';
        $allowedTemplateKeys = $this->gate->allowedTemplateKeys(auth()->user());

        return Page::query()
            ->withCount('sections')
            ->when(
                $allowedTemplateKeys !== null,
                fn ($query) => $query->whereIn('template_key', $allowedTemplateKeys)
            )
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('title', 'like', '%'.$search.'%')
                        ->orWhere('slug', 'like', '%'.$search.'%')
                        ->orWhere('template_key', 'like', '%'.$search.'%');
                });
            })
            ->when(
                $this->statusFilter !== '',
                fn ($query) => $query->where('workflow_status', $this->statusFilter)
            )
            ->orderBy($sortField, $sortDirection)
            ->orderBy('id');
    }

    private function hasTemplateAccess(Page $page): bool
    {
        return $this->gate->hasAnyGrantForTemplate(auth()->user(), $page->template_key);
    }

    private function authorizeTemplateAccess(Page $page): void
    {
        abort_unless($this->hasTemplateAccess($page), 403);
    }

    private function sortableFields(): array
    {
        return ['title', 'template_key', 'sections_count', 'workflow_status', 'updated_at'];
    }

    private function csvValue(?string $value): string
    {
        $value ??= '';

        return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
    }

    private function authorizeAccess(): void
    {
        $user = auth()->user();
        abort_unless($user?->can('access admin'), 403);
        abort_unless($user->can('manage pages') || $this->gate->hasAnyGrant($user), 403);
    }
}
