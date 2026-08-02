<div>
    <div class="admin-page-heading">
        <div><h2>Operations overview</h2><p>Publishing, schedule, inbox, and media activity.</p></div>
        @can('manage pages')<a class="admin-button" href="{{ route('admin.pages') }}" wire:navigate>Review pages</a>@endcan
    </div>
    <div class="admin-stats">
        @foreach ($stats as $label => $value)
            <article class="admin-stat"><span>{{ $label }}</span><strong>{{ $value }}</strong></article>
        @endforeach
    </div>
    <div class="admin-grid">
        @can('manage schedule')
            <section class="admin-panel half">
                <div class="admin-panel-header"><h3>Upcoming games</h3><a href="{{ route('admin.resources', 'games') }}" wire:navigate>Manage</a></div>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Date</th><th>Matchup</th></tr></thead>
                        <tbody>
                        @forelse ($upcomingGames as $game)
                            <tr><td>{{ $game->starts_at->format('M j, Y g:i A') }}</td><td>{{ $game->awayTeam->name }} at {{ $game->homeTeam->name }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="admin-empty">No upcoming games.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endcan
        @can('manage submissions')
            <section class="admin-panel half">
                <div class="admin-panel-header"><h3>Recent submissions</h3><a href="{{ route('admin.submissions') }}" wire:navigate>Open inbox</a></div>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Received</th><th>Type</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse ($recentSubmissions as $submission)
                            <tr>
                                <td>{{ $submission->created_at->diffForHumans() }}</td>
                                <td>{{ ucfirst($submission->type) }}</td>
                                <td><span class="status-badge {{ $submission->status->value }}">{{ str($submission->status->value)->headline() }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="admin-empty">No submissions.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endcan
    </div>
</div>
