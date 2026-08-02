@foreach ($games as $game)
@php($result = $game->resultForHomeTeam())
<tr class="{{ $game->is_featured ? 'featured-game' : '' }}" data-location="{{ $game->isHomeGame() ? 'home' : 'away' }}" data-game-date="{{ $game->starts_at->format('Y-m-d') }}">
    <td>{{ $game->starts_at->format('D, M d, Y') }}</td>
    <td><span class="team-badge">{{ $game->opponent()?->abbreviation }}</span>{{ $game->opponent()?->name }}</td>
    <td>{{ $game->isHomeGame() ? 'Home' : 'Away' }}</td>
    <td>{{ $game->starts_at->format('g:i A') }}</td>
    <td class="{{ $result ? (str_starts_with($result, 'W') ? 'win' : 'loss') : 'upcoming' }}">{{ $result ?: 'Upcoming' }}</td>
    <td>@if($game->starts_at->isFuture() && $game->ticket_url)<a href="{{ $game->ticket_url }}" target="_blank" rel="noopener noreferrer">Tickets</a>@else<a href="{{ route('games.show', $game) }}">View</a>@endif</td>
</tr>
@endforeach
