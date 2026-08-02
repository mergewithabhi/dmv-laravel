<?php

namespace App\Services;

use App\Models\Game;

class ScheduleDataService
{
    public function calendarData(): array
    {
        $games = Game::query()
            ->published()
            ->whereHas('season', fn ($query) => $query->published())
            ->whereHas('homeTeam', fn ($query) => $query->published())
            ->whereHas('awayTeam', fn ($query) => $query->published())
            ->with(['homeTeam', 'awayTeam'])
            ->orderBy('starts_at')
            ->get();
        $nextGame = $games->first(fn (Game $game) => $game->starts_at->isFuture());
        $events = [];

        foreach ($games as $game) {
            $monthKey = $game->starts_at->format('Y-').((int) $game->starts_at->format('n') - 1);
            $events[$monthKey][(int) $game->starts_at->format('j')] = $game->isHomeGame() ? 'home' : 'away';
        }

        return [
            'calendarEvents' => $events,
            'calendarStart' => ($nextGame?->starts_at ?: now())->startOfMonth()->toIso8601String(),
            'selectedDate' => $nextGame?->starts_at->format('Y-m-d'),
            'calendarDownload' => route('schedule.calendar'),
        ];
    }
}
