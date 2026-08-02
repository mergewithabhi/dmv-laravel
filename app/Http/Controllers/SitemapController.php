<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Page;
use App\Models\Person;
use App\Models\Post;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = collect();

        Page::query()->published()->where('is_indexable', true)->get()->each(
            fn (Page $page) => $urls->push([
                'location' => $page->slug === 'home' ? url('/') : url('/'.$page->slug),
                'modified' => $page->updated_at,
            ])
        );
        Post::query()->published()->get()->each(
            fn (Post $post) => $urls->push([
                'location' => route('news.show', $post->slug),
                'modified' => $post->updated_at,
            ])
        );
        Person::query()->published()->where('type', 'player')->get()->each(
            fn (Person $person) => $urls->push([
                'location' => route('players.show', $person->slug),
                'modified' => $person->updated_at,
            ])
        );
        Game::query()
            ->published()
            ->whereHas('season', fn ($query) => $query->published())
            ->whereHas('homeTeam', fn ($query) => $query->published())
            ->whereHas('awayTeam', fn ($query) => $query->published())
            ->get()
            ->each(
                fn (Game $game) => $urls->push([
                    'location' => route('games.show', $game->slug),
                    'modified' => $game->updated_at,
                ])
            );

        return response()
            ->view('seo.sitemap', compact('urls'))
            ->header('Content-Type', 'application/xml; charset=utf-8');
    }
}
