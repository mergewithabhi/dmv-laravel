<main id="site-main" class="dynamic-page">
    <section class="container game-detail">
        <a href="{{ route('site.page', ['slug' => 'schedule']) }}" wire:navigate>Back to schedule</a>
        <p>{{ $game->season->name }} &middot; {{ $game->starts_at->format('l, F j, Y') }} &middot; {{ $game->starts_at->format('g:i A') }}</p>
        <div class="game-detail-matchup">
            <div>
                @if($game->awayTeam->logo)<img src="{{ $game->awayTeam->logo->url() }}" alt="{{ $game->awayTeam->name }}">@endif
                <h1>{{ $game->awayTeam->name }}</h1>
                @if($game->away_score !== null)<strong>{{ $game->away_score }}</strong>@endif
            </div>
            <span>{{ $game->status->value === 'final' ? 'FINAL' : 'VS' }}</span>
            <div>
                @if($game->homeTeam->logo)<img src="{{ $game->homeTeam->logo->url() }}" alt="{{ $game->homeTeam->name }}">@endif
                <h1>{{ $game->homeTeam->name }}</h1>
                @if($game->home_score !== null)<strong>{{ $game->home_score }}</strong>@endif
            </div>
        </div>
        <div class="game-detail-info">
            <p><strong>Venue</strong>{{ $game->venue?->name }}<br>{{ $game->venue?->formattedAddress() }}</p>
            @if($game->ticket_url && $game->starts_at->isFuture())
                <a class="button button-primary" href="{{ $game->ticket_url }}" target="_blank" rel="noopener noreferrer">Tickets</a>
            @endif
        </div>
    </section>
</main>
