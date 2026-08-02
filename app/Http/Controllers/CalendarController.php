<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Season;
use Illuminate\Http\Response;

class CalendarController extends Controller
{
    public function __invoke(?string $season = null): Response
    {
        $seasonModel = $season
            ? Season::query()->published()->where('slug', $season)->firstOrFail()
            : Season::query()->published()->where('is_current', true)->first();

        $games = Game::query()
            ->published()
            ->whereHas('season', fn ($query) => $query->published())
            ->whereHas('homeTeam', fn ($query) => $query->published())
            ->whereHas('awayTeam', fn ($query) => $query->published())
            ->where(fn ($query) => $query
                ->whereNull('venue_id')
                ->orWhereHas('venue', fn ($query) => $query->published()))
            ->with(['homeTeam', 'awayTeam', 'venue'])
            ->when($seasonModel, fn ($query) => $query->whereBelongsTo($seasonModel))
            ->orderBy('starts_at')
            ->get();

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//DMV Warriors//Schedule//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:DMV Warriors Schedule',
        ];

        foreach ($games as $game) {
            $start = $game->starts_at->copy()->utc();
            $end = $start->copy()->addHours(2);
            $summary = $this->escape($game->awayTeam->name.' at '.$game->homeTeam->name);
            $location = $this->escape($game->venue?->formattedAddress() ?: 'Venue to be announced');

            array_push(
                $lines,
                'BEGIN:VEVENT',
                'UID:game-'.$game->id.'@dmvwarriors.com',
                'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
                'DTSTART:'.$start->format('Ymd\THis\Z'),
                'DTEND:'.$end->format('Ymd\THis\Z'),
                'SUMMARY:'.$summary,
                'LOCATION:'.$location,
                'URL:'.route('games.show', $game->slug),
                'STATUS:'.($game->status->value === 'cancelled' ? 'CANCELLED' : 'CONFIRMED'),
                'END:VEVENT'
            );
        }

        $lines[] = 'END:VCALENDAR';
        $name = 'dmv-warriors-'.($seasonModel?->slug ?: 'schedule').'.ics';

        return response(implode("\r\n", $lines)."\r\n", 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    private function escape(string $value): string
    {
        return str_replace(
            ['\\', "\r\n", "\n", ',', ';'],
            ['\\\\', '\\n', '\\n', '\\,', '\\;'],
            $value
        );
    }
}
