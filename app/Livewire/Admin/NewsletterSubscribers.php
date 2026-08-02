<?php

namespace App\Livewire\Admin;

use App\Jobs\SyncNewsletterSubscriber;
use App\Jobs\UnsubscribeNewsletterSubscriber;
use App\Models\NewsletterSubscriber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
class NewsletterSubscribers extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $sortField = 'created_at';

    public string $sortDirection = 'desc';

    public int $perPage = 15;

    public function mount(): void
    {
        $this->authorizeAccess();
    }

    public function updatedSearch(): void
    {
        $this->filtersChanged();
    }

    public function updatedStatusFilter(): void
    {
        $this->filtersChanged();
    }

    public function updatedPerPage(): void
    {
        $this->authorizeAccess();
        if (! in_array($this->perPage, $this->allowedPageSizes(), true)) {
            $this->perPage = 15;
        }
        $this->resetPage();
    }

    public function updatingPaginators(): void
    {
        $this->authorizeAccess();
    }

    public function sortBy(string $field): void
    {
        $this->authorizeAccess();
        abort_unless(in_array($field, $this->sortableFields(), true), 422);

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = $field === 'created_at' ? 'desc' : 'asc';
        }

        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->authorizeAccess();
        $this->reset(['search', 'statusFilter']);
        $this->resetPage();
    }

    public function unsubscribe(int $id): void
    {
        $this->authorizeAccess();

        DB::transaction(function () use ($id): void {
            $subscriber = NewsletterSubscriber::query()->lockForUpdate()->findOrFail($id);

            if ($subscriber->status === 'unsubscribed') {
                return;
            }

            $subscriber->forceFill([
                'status' => 'unsubscribed',
                'unsubscribed_at' => now(),
                'last_error' => null,
            ])->save();

            activity('privacy')
                ->causedBy(auth()->user())
                ->performedOn($subscriber)
                ->log('unsubscribed newsletter subscriber');

            UnsubscribeNewsletterSubscriber::dispatch($subscriber)->afterCommit();
        });

        session()->flash('success', 'Subscriber unsubscribe was queued.');
    }

    public function retrySync(int $id): void
    {
        $this->authorizeAccess();

        DB::transaction(function () use ($id): void {
            $subscriber = NewsletterSubscriber::query()->lockForUpdate()->findOrFail($id);
            abort_if(
                $subscriber->status === 'unsubscribed',
                422,
                'Unsubscribed contacts cannot be synchronized.'
            );

            $subscriber->forceFill([
                'status' => 'pending',
                'provider' => config('services.newsletter.driver', 'log'),
                'last_error' => null,
            ])->save();

            activity('cms')
                ->causedBy(auth()->user())
                ->performedOn($subscriber)
                ->log('retried newsletter subscriber sync');

            SyncNewsletterSubscriber::dispatch($subscriber)->afterCommit();
        });

        session()->flash('success', 'Subscriber synchronization queued.');
    }

    public function delete(int $id): void
    {
        $this->authorizeAccess();
        $subscriber = NewsletterSubscriber::query()->findOrFail($id);
        abort_if(
            $subscriber->status !== 'unsubscribed'
                || $subscriber->last_error
                || ! $subscriber->last_synced_at
                || ! $subscriber->unsubscribed_at
                || $subscriber->last_synced_at->lt($subscriber->unsubscribed_at),
            422,
            'Confirm provider unsubscribe synchronization before deleting this subscriber.'
        );

        activity('privacy')
            ->causedBy(auth()->user())
            ->performedOn($subscriber)
            ->log('deleted newsletter subscriber');

        $subscriber->delete();
        session()->flash('success', 'Subscriber permanently deleted.');
    }

    public function export()
    {
        $this->authorizeAccess();
        abort_unless(auth()->user()->can('export submissions'), 403);

        activity('privacy')
            ->causedBy(auth()->user())
            ->log('exported newsletter subscribers');

        $query = $this->subscribersQuery();

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, [
                'UUID',
                'Email',
                'Status',
                'Consent',
                'Provider',
                'Provider ID',
                'Subscribed at',
                'Unsubscribed at',
                'Last synced at',
                'Last error',
                'Created at',
            ]);

            foreach ($query->cursor() as $subscriber) {
                fputcsv($output, [
                    $subscriber->uuid,
                    $this->csvValue($subscriber->email),
                    $this->displayStatus($subscriber),
                    $subscriber->consent ? 'Yes' : 'No',
                    $this->csvValue($subscriber->provider),
                    $this->csvValue($subscriber->provider_id),
                    $subscriber->subscribed_at?->toIso8601String(),
                    $subscriber->unsubscribed_at?->toIso8601String(),
                    $subscriber->last_synced_at?->toIso8601String(),
                    $this->csvValue($subscriber->last_error),
                    $subscriber->created_at->toIso8601String(),
                ]);
            }

            fclose($output);
        }, 'dmv-newsletter-subscribers-'.now()->format('Y-m-d-His').'.csv');
    }

    public function render()
    {
        $this->authorizeAccess();
        $perPage = in_array($this->perPage, $this->allowedPageSizes(), true)
            ? $this->perPage
            : 15;

        return view('livewire.admin.newsletter-subscribers', [
            'subscribers' => $this->subscribersQuery()->paginate($perPage),
        ])->title('Newsletter Subscribers')
            ->layoutData(['heading' => 'Newsletter Subscribers']);
    }

    private function subscribersQuery(): Builder
    {
        $search = trim($this->search);
        $sortField = in_array($this->sortField, $this->sortableFields(), true)
            ? $this->sortField
            : 'created_at';
        $sortDirection = $this->sortDirection === 'asc' ? 'asc' : 'desc';
        $statusFilter = in_array(
            $this->statusFilter,
            ['', 'pending', 'failed', 'subscribed', 'unsubscribed'],
            true
        ) ? $this->statusFilter : '';

        return NewsletterSubscriber::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $emailHash = hash('sha256', strtolower($search));
                $query->where(function (Builder $query) use ($search, $emailHash): void {
                    $query->where('uuid', 'like', '%'.$search.'%')
                        ->orWhere('provider', 'like', '%'.$search.'%')
                        ->orWhere('provider_id', 'like', '%'.$search.'%')
                        ->orWhere('email_hash', $emailHash);
                });
            })
            ->when(
                $statusFilter === 'pending',
                fn (Builder $query) => $query
                    ->where('status', 'pending')
                    ->whereNull('last_error')
            )
            ->when(
                $statusFilter === 'failed',
                fn (Builder $query) => $query->whereNotNull('last_error')
            )
            ->when(
                in_array($statusFilter, ['subscribed', 'unsubscribed'], true),
                fn (Builder $query) => $query->where('status', $statusFilter)
            )
            ->orderBy($sortField, $sortDirection)
            ->orderBy('id', $sortDirection);
    }

    private function displayStatus(NewsletterSubscriber $subscriber): string
    {
        return filled($subscriber->last_error) ? 'failed' : $subscriber->status;
    }

    private function filtersChanged(): void
    {
        $this->authorizeAccess();
        $this->resetPage();
    }

    private function sortableFields(): array
    {
        return ['status', 'provider', 'subscribed_at', 'last_synced_at', 'created_at'];
    }

    private function allowedPageSizes(): array
    {
        return [10, 15, 25, 50];
    }

    private function csvValue(?string $value): string
    {
        $value ??= '';

        return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
    }

    private function authorizeAccess(): void
    {
        abort_unless(auth()->user()?->can('manage submissions'), 403);
    }
}
