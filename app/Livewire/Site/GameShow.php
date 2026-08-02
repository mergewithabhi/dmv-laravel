<?php

namespace App\Livewire\Site;

use App\Livewire\Site\Concerns\BuildsSiteLayoutData;
use App\Models\Game;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.site')]
class GameShow extends Component
{
    use BuildsSiteLayoutData;

    public Game $game;

    public function mount(Game $game): void
    {
        abort_unless(
            Game::query()
                ->published()
                ->whereKey($game)
                ->whereHas('season', fn ($query) => $query->published())
                ->whereHas('homeTeam', fn ($query) => $query->published())
                ->whereHas('awayTeam', fn ($query) => $query->published())
                ->exists(),
            404
        );
        $this->game = $game->load(['homeTeam.logo', 'awayTeam.logo', 'venue', 'season']);
    }

    public function render()
    {
        $title = $this->game->awayTeam->name.' at '.$this->game->homeTeam->name;
        $meta = [
            'title' => $title.' | DMV Warriors',
            'description' => $title.' on '.$this->game->starts_at->format('F j, Y').' at '.$this->game->venue?->name.'.',
            'canonical' => route('games.show', $this->game),
            'page_key' => 'schedule',
        ];

        return view('livewire.site.game-show', compact('title'))
            ->title($meta['title'])
            ->layoutData($this->siteLayoutData($meta, [
                '@context' => 'https://schema.org',
                '@type' => 'SportsEvent',
                'name' => $title,
                'startDate' => $this->game->starts_at->toIso8601String(),
                'location' => [
                    '@type' => 'Place',
                    'name' => $this->game->venue?->name,
                    'address' => $this->game->venue?->formattedAddress(),
                ],
                'url' => $meta['canonical'],
            ]));
    }
}
