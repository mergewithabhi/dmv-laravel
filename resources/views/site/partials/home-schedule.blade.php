@foreach ($games as $game)
<tr>
    <td>{{ $game->starts_at->format('M j, Y') }}</td>
    <td><b>{{ $game->opponent()?->abbreviation }}</b> {{ $game->opponent()?->name }}</td>
    <td>{{ $game->isHomeGame() ? 'Home' : 'Away' }}<br><small>{{ $game->starts_at->format('g:i A') }}</small></td>
    <td><a href="{{ route('games.show', $game) }}"><strong>Upcoming</strong></a></td>
</tr>
@endforeach
