<?php

namespace App\Livewire\Site;

use App\Livewire\Site\Concerns\BuildsSiteLayoutData;
use App\Models\Person;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.site')]
class PlayerShow extends Component
{
    use BuildsSiteLayoutData;

    public Person $person;

    public function mount(Person $person): void
    {
        abort_unless(
            $person->type === 'player'
                && Person::query()->published()->whereKey($person)->exists(),
            404
        );
        $this->person = $person->load([
            'photo',
            'rosterMemberships' => fn ($query) => $query
                ->whereHas('season', fn ($query) => $query->published())
                ->with('season'),
        ]);
    }

    public function render()
    {
        $membership = $this->person->rosterMemberships->sortByDesc('season.starts_on')->first();
        $meta = [
            'title' => $this->person->display_name.' | DMV Warriors Roster',
            'description' => $this->person->biography ?: 'DMV Warriors player profile for '.$this->person->display_name.'.',
            'canonical' => route('players.show', $this->person),
            'og_image' => $this->person->photo?->url('web'),
            'page_key' => 'roster',
        ];

        return view('livewire.site.player-show', compact('membership'))
            ->title($meta['title'])
            ->layoutData($this->siteLayoutData($meta, [
                '@context' => 'https://schema.org',
                '@type' => 'Person',
                'name' => $this->person->display_name,
                'memberOf' => ['@type' => 'SportsTeam', 'name' => 'DMV Warriors'],
                'url' => $meta['canonical'],
            ]));
    }
}
