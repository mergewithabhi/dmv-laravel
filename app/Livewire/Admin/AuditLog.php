<?php

namespace App\Livewire\Admin;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

#[Layout('components.layouts.admin')]
class AuditLog extends Component
{
    use WithPagination;

    public string $search = '';

    public function mount(): void
    {
        $this->authorizeAccess();
    }

    public function updatedSearch(): void
    {
        $this->authorizeAccess();
        $this->resetPage();
    }

    public function render()
    {
        $this->authorizeAccess();
        $activities = Activity::query()
            ->with('causer')
            ->when($this->search, fn ($query) => $query->where('description', 'like', '%'.$this->search.'%'))
            ->latest()
            ->paginate(30);

        return view('livewire.admin.audit-log', compact('activities'))
            ->title('Audit Log')
            ->layoutData(['heading' => 'Audit Log']);
    }

    private function authorizeAccess(): void
    {
        abort_unless(auth()->user()?->can('view audit log'), 403);
    }
}
