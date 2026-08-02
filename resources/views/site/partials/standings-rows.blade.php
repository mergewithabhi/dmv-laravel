@foreach ($standings as $standing)
<tr class="{{ $standing->team->is_home_team ? 'standings-current' : '' }}"><td>{{ $standing->rank }}</td><td><span class="team-badge">{{ $standing->team->abbreviation }}</span>{{ $standing->team->name }}</td><td>{{ $standing->wins }}</td><td>{{ $standing->losses }}</td><td>{{ ltrim(number_format($standing->win_percentage, 3), '0') }}</td></tr>
@endforeach
