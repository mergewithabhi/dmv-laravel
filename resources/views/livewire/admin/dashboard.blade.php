<div>
    <div class="admin-page-heading">
        <div><h2>Welcome back, {{ auth()->user()->name }}</h2><p>Choose what you want to update.</p></div>
        <a class="admin-button secondary" href="{{ route('home') }}" target="_blank" rel="noopener">View live website</a>
    </div>

    <div class="admin-quick-actions">
        @if (auth()->user()->can('manage pages') || auth()->user()->contentPermissions()->exists())
            <a href="{{ route('admin.pages') }}" wire:navigate><img src="{{ asset('assets/icons/clipboard.svg') }}" alt=""><span><strong>Edit website pages</strong><small>Text, buttons, and page images</small></span></a>
        @endif
        @can('manage schedule')<a href="{{ route('admin.resources', 'games') }}" wire:navigate><img src="{{ asset('assets/icons/calendar.svg') }}" alt=""><span><strong>Update games</strong><small>Dates, scores, tickets, and venues</small></span></a>@endcan
        @can('manage roster')<a href="{{ route('admin.resources', 'people') }}" wire:navigate><img src="{{ asset('assets/icons/users.svg') }}" alt=""><span><strong>Manage team</strong><small>Players, coaches, photos, and bios</small></span></a>@endcan
        @can('manage news')<a href="{{ route('admin.resources', 'posts') }}" wire:navigate><img src="{{ asset('assets/icons/mail.svg') }}" alt=""><span><strong>Publish news</strong><small>Stories and featured images</small></span></a>@endcan
        @can('manage media')<a href="{{ route('admin.media') }}" wire:navigate><img src="{{ asset('assets/icons/star.svg') }}" alt=""><span><strong>Images and files</strong><small>Upload and organize media</small></span></a>@endcan
        @can('manage submissions')<a href="{{ route('admin.submissions') }}" wire:navigate><img src="{{ asset('assets/icons/mail.svg') }}" alt=""><span><strong>Read messages</strong><small>Contact and sponsor requests</small></span></a>@endcan
    </div>

    @if ($stats)
        <div class="admin-stats">
            @foreach ($stats as $label => $value)<article class="admin-stat"><span>{{ $label }}</span><strong>{{ $value }}</strong></article>@endforeach
        </div>
    @endif

    <div class="admin-grid">
        @if ($recentContent->isNotEmpty())
            <section class="admin-panel half">
                <div class="admin-panel-header"><h3>Recently updated</h3></div>
                <div class="admin-simple-list">
                    @foreach ($recentContent as $item)
                        <a href="{{ $item['url'] }}" wire:navigate><span><strong>{{ $item['title'] }}</strong><small>{{ $item['type'] }}</small></span><time>{{ $item['updated_at']->diffForHumans() }}</time></a>
                    @endforeach
                </div>
            </section>
        @endif
        @can('manage schedule')
            <section class="admin-panel half">
                <div class="admin-panel-header"><h3>Upcoming games</h3><a href="{{ route('admin.resources', 'games') }}" wire:navigate>Manage</a></div>
                <div class="admin-simple-list">
                    @forelse ($upcomingGames as $game)
                        <a href="{{ route('admin.resources', 'games') }}" wire:navigate><span><strong>{{ $game->awayTeam->name }} at {{ $game->homeTeam->name }}</strong><small>{{ $game->starts_at->format('M j, Y g:i A') }}</small></span></a>
                    @empty
                        <p class="admin-empty">No upcoming games.</p>
                    @endforelse
                </div>
            </section>
        @endcan
    </div>
</div>
