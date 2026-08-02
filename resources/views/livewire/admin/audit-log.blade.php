<div>
    <div class="admin-page-heading"><div><h2>Audit log</h2><p>Recorded administrative and security events.</p></div></div>
    <section class="admin-panel">
        <div class="admin-panel-body"><div class="admin-toolbar"><input type="search" wire:model.live.debounce.300ms="search" placeholder="Search events"></div></div>
        <div class="admin-table-wrap"><table class="admin-table">
            <thead><tr><th>Time</th><th>User</th><th>Log</th><th>Event</th><th>Subject</th></tr></thead>
            <tbody>@forelse($activities as $activity)<tr><td>{{ $activity->created_at->format('M j, Y g:i A') }}</td><td>{{ $activity->causer?->name ?? 'System' }}</td><td>{{ $activity->log_name }}</td><td>{{ $activity->description }}</td><td>{{ class_basename($activity->subject_type ?? '') }} #{{ $activity->subject_id }}</td></tr>@empty<tr><td colspan="5" class="admin-empty">No activity recorded.</td></tr>@endforelse</tbody>
        </table></div>
        @include('livewire.admin.partials.pagination', ['paginator' => $activities])
    </section>
</div>
