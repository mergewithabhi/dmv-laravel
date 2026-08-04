<?php

namespace App\Livewire\Admin;

use App\Models\FormSubmission;
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

    #[Locked]
    public ?int $selectedId = null;

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

    public function select(int $id): void
    {
        $this->authorizeAccess();
        FormSubmission::query()->findOrFail($id);
        $this->selectedId = $id;
    }

    public function closeSelection(): void
    {
        $this->authorizeAccess();
        $this->selectedId = null;
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
            fputcsv($output, ['UUID', 'Received', 'Type', 'Name', 'Email', 'Phone', 'Subject', 'Consent', 'Payload']);
            foreach ($rows as $row) {
                fputcsv($output, [
                    $row->uuid,
                    $row->created_at->toIso8601String(),
                    $row->type,
                    $this->csvValue($row->name),
                    $this->csvValue($row->email),
                    $this->csvValue($row->phone),
                    $this->csvValue($row->subject),
                    $row->consent ? 'Yes' : 'No',
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
        return view('livewire.admin.submissions-inbox', [
            'submissions' => $submissions,
            'selected' => $selected,
        ])->title('Submissions')->layoutData(['heading' => 'Submissions']);
    }

    private function baseQuery()
    {
        return FormSubmission::query()
            ->when($this->typeFilter, fn ($query) => $query->where('type', $this->typeFilter))
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
