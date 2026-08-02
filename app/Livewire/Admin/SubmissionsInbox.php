<?php

namespace App\Livewire\Admin;

use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
class SubmissionsInbox extends Component
{
    use WithPagination;

    public string $search = '';

    public string $typeFilter = '';

    public string $statusFilter = '';

    #[Locked]
    public ?int $selectedId = null;

    public string $selectedStatus = 'new';

    public ?int $assignedTo = null;

    public string $internalNotes = '';

    public function mount(?FormSubmission $submission = null): void
    {
        $this->authorizeAccess();

        if ($submission) {
            $this->select($submission->id);
        }
    }

    public function updatedSearch(): void
    {
        $this->filtersChanged();
    }

    public function updatedTypeFilter(): void
    {
        $this->filtersChanged();
    }

    public function updatedStatusFilter(): void
    {
        $this->filtersChanged();
    }

    public function select(int $id): void
    {
        $this->authorizeAccess();
        $submission = FormSubmission::query()->findOrFail($id);
        $this->selectedId = $id;
        $this->selectedStatus = $submission->status->value;
        $this->assignedTo = $submission->assigned_to;
        $this->internalNotes = $submission->internal_notes ?? '';
        $this->resetValidation();
    }

    public function closeSelection(): void
    {
        $this->authorizeAccess();
        $this->selectedId = null;
        $this->selectedStatus = 'new';
        $this->assignedTo = null;
        $this->internalNotes = '';
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->authorizeAccess();
        if (! $this->selectedId) {
            throw ValidationException::withMessages([
                'selectedStatus' => 'Select a submission before saving.',
            ]);
        }

        $validated = $this->validate([
            'selectedStatus' => ['required', 'in:new,in_progress,resolved,spam,archived'],
            'assignedTo' => ['nullable', 'exists:users,id'],
            'internalNotes' => ['nullable', 'string', 'max:10000'],
        ]);
        if (
            $validated['assignedTo']
            && ! User::permission('manage submissions')->whereKey($validated['assignedTo'])->exists()
        ) {
            throw ValidationException::withMessages([
                'assignedTo' => 'Assign submissions only to a user with inbox access.',
            ]);
        }
        $submission = FormSubmission::query()->findOrFail($this->selectedId);
        $submission->update([
            'status' => $validated['selectedStatus'],
            'assigned_to' => $validated['assignedTo'],
            'internal_notes' => $validated['internalNotes'],
        ]);
        activity('cms')->causedBy(auth()->user())->performedOn($submission)->log('updated submission');
        session()->flash('success', 'Submission updated.');
    }

    public function delete(int $id): void
    {
        $this->authorizeAccess();
        $submission = FormSubmission::query()->findOrFail($id);
        activity('privacy')->causedBy(auth()->user())->performedOn($submission)->log('deleted submission');
        $submission->delete();
        if ($this->selectedId === $id) {
            $this->closeSelection();
        }
        session()->flash('success', 'Submission permanently deleted.');
    }

    public function export()
    {
        $this->authorizeAccess();
        abort_unless(auth()->user()->can('export submissions'), 403);
        $rows = $this->baseQuery()->get();
        FormSubmission::query()->whereKey($rows->modelKeys())->update(['exported_at' => now()]);

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['UUID', 'Received', 'Type', 'Status', 'Name', 'Email', 'Phone', 'Subject', 'Payload']);
            foreach ($rows as $row) {
                fputcsv($output, [
                    $row->uuid,
                    $row->created_at->toIso8601String(),
                    $row->type,
                    $row->status->value,
                    $this->csvValue($row->name),
                    $this->csvValue($row->email),
                    $this->csvValue($row->phone),
                    $this->csvValue($row->subject),
                    $this->csvValue((string) json_encode($row->payload, JSON_UNESCAPED_SLASHES)),
                ]);
            }
            fclose($output);
        }, 'dmv-submissions-'.now()->format('Y-m-d').'.csv');
    }

    public function render()
    {
        $this->authorizeAccess();
        $submissions = $this->baseQuery()->paginate(20);
        $selected = $this->selectedId ? FormSubmission::query()->find($this->selectedId) : null;
        if ($this->selectedId && ! $selected) {
            $this->selectedId = null;
        }
        $users = User::permission('manage submissions')->orderBy('name')->get();

        return view('livewire.admin.submissions-inbox', [
            'submissions' => $submissions,
            'selected' => $selected,
            'users' => $users,
        ])->title('Submissions')->layoutData(['heading' => 'Submissions']);
    }

    private function baseQuery()
    {
        return FormSubmission::query()
            ->when($this->typeFilter, fn ($query) => $query->where('type', $this->typeFilter))
            ->when($this->statusFilter, fn ($query) => $query->where('status', $this->statusFilter))
            ->when($this->search, function ($query): void {
                $search = trim($this->search);
                $query->where(function ($query) use ($search): void {
                    $query->where('uuid', 'like', '%'.$search.'%')
                        ->orWhere('type', 'like', '%'.$search.'%')
                        ->orWhere('email_hash', hash('sha256', strtolower($search)));
                });
            })
            ->latest();
    }

    private function csvValue(?string $value): string
    {
        $value ??= '';

        return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
    }

    private function filtersChanged(): void
    {
        $this->authorizeAccess();
        $this->closeSelection();
        $this->resetPage();
    }

    private function authorizeAccess(): void
    {
        abort_unless(auth()->user()?->can('manage submissions'), 403);
    }
}
