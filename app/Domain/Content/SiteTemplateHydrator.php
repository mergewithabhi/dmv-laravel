<?php

namespace App\Domain\Content;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Post;
use App\Models\Season;
use App\Models\SponsorTier;
use DOMDocument;
use DOMElement;
use DOMXPath;

class SiteTemplateHydrator
{
    public function hydrate(string $templateKey, DOMDocument $document, DOMElement $root): void
    {
        $season = Season::query()
            ->published()
            ->where('is_current', true)
            ->first();
        $nextGame = Game::query()
            ->published()
            ->upcoming()
            ->whereHas('season', fn ($query) => $query->published())
            ->whereHas('homeTeam', fn ($query) => $query->published())
            ->whereHas('awayTeam', fn ($query) => $query->published())
            ->where(fn ($query) => $query
                ->whereNull('venue_id')
                ->orWhereHas('venue', fn ($query) => $query->published()))
            ->with(['homeTeam.logo', 'awayTeam.logo', 'venue'])
            ->first();

        $this->hydrateNextGame($document, $root, $nextGame);

        match ($templateKey) {
            'home' => $this->hydrateHome($document, $root, $season),
            'about' => $this->hydrateAbout($document, $root, $season),
            'roster' => $this->hydrateRoster($document, $root, $season),
            'schedule' => $this->hydrateSchedule($document, $root, $season),
            'sponsors' => $this->hydrateSponsors($document, $root),
            default => null,
        };
    }

    private function hydrateHome(DOMDocument $document, DOMElement $root, ?Season $season): void
    {
        $posts = Post::query()->published()->with('featuredMedia')->latest('published_at')->limit(3)->get();
        $players = $season?->rosterMemberships()
            ->with('person.photo')
            ->whereHas('person', fn ($query) => $query->published())
            ->where('is_active', true)
            ->where('is_featured', true)
            ->orderBy('position_order')
            ->limit(4)
            ->get() ?? collect();
        $games = Game::query()
            ->published()
            ->upcoming()
            ->whereHas('season', fn ($query) => $query->published())
            ->whereHas('homeTeam', fn ($query) => $query->published())
            ->whereHas('awayTeam', fn ($query) => $query->published())
            ->where(fn ($query) => $query
                ->whereNull('venue_id')
                ->orWhereHas('venue', fn ($query) => $query->published()))
            ->with(['homeTeam', 'awayTeam'])
            ->limit(3)
            ->get();

        $this->replaceClass($document, $root, 'news-grid', view('site.partials.home-news', compact('posts'))->render());
        $this->replaceClass($document, $root, 'players', view('site.partials.home-players', compact('players'))->render());

        if ($table = $this->first($document, $root, ".//*[contains(concat(' ', normalize-space(@class), ' '), ' schedule-section ')]//tbody")) {
            $this->replaceInner($document, $table, view('site.partials.home-schedule', compact('games'))->render());
        }

        $this->hydratePartnerLogos($document, $root, 'partner-logos');
        $this->setLink($document, $root, ".//*[contains(concat(' ', normalize-space(@class), ' '), ' news-section ')]//a[1]", route('news.index'));
    }

    private function hydrateAbout(DOMDocument $document, DOMElement $root, ?Season $season): void
    {
        $staff = $season?->staffAssignments()
            ->with('person.photo')
            ->whereHas('person', fn ($query) => $query->published())
            ->where('is_active', true)
            ->where('is_leadership', true)
            ->orderBy('position_order')
            ->get() ?? collect();

        $this->replaceClass(
            $document,
            $root,
            'leadership-grid',
            view('site.partials.leadership', compact('staff'))->render()
        );
    }

    private function hydrateRoster(DOMDocument $document, DOMElement $root, ?Season $season): void
    {
        $players = $season?->rosterMemberships()
            ->with('person.photo')
            ->whereHas('person', fn ($query) => $query->published())
            ->where('is_active', true)
            ->orderBy('position_order')
            ->get() ?? collect();
        $staff = $season?->staffAssignments()
            ->with('person.photo')
            ->whereHas('person', fn ($query) => $query->published())
            ->where('is_active', true)
            ->orderBy('position_order')
            ->get() ?? collect();

        $this->replaceClass($document, $root, 'full-roster-grid', view('site.partials.roster-players', compact('players'))->render());
        $this->replaceClass($document, $root, 'coaching-grid', view('site.partials.coaches', compact('staff'))->render());
    }

    private function hydrateSchedule(DOMDocument $document, DOMElement $root, ?Season $season): void
    {
        if (! $season) {
            return;
        }

        $games = $season->games()
            ->published()
            ->whereHas('homeTeam', fn ($query) => $query->published())
            ->whereHas('awayTeam', fn ($query) => $query->published())
            ->where(fn ($query) => $query
                ->whereNull('venue_id')
                ->orWhereHas('venue', fn ($query) => $query->published()))
            ->with(['homeTeam', 'awayTeam', 'venue.image'])
            ->orderBy('starts_at')
            ->get();
        $standings = $season->standings()
            ->whereHas('team', fn ($query) => $query->published())
            ->with('team')
            ->get();

        if ($table = $this->first($document, $root, ".//*[contains(concat(' ', normalize-space(@class), ' '), ' season-table ')]//tbody")) {
            $this->replaceInner($document, $table, view('site.partials.schedule-rows', compact('games'))->render());
        }
        if ($table = $this->first($document, $root, ".//*[contains(concat(' ', normalize-space(@class), ' '), ' standings-panel ')]//tbody")) {
            $this->replaceInner($document, $table, view('site.partials.standings-rows', compact('standings'))->render());
        }

        $this->hydratePartnerLogos($document, $root, 'schedule-partners');
        $this->hydrateSeasonStats($document, $root, $season, $games);
        $this->hydrateVenue($document, $root, $games->first(fn (Game $game) => $game->venue)?->venue);
        $this->setFirstText($document, $root, ".//*[contains(concat(' ', normalize-space(@class), ' '), ' schedule-panel-heading ')]//h2", $season->name.' Schedule');
    }

    private function hydrateSponsors(DOMDocument $document, DOMElement $root): void
    {
        $tiers = SponsorTier::query()
            ->with(['sponsors' => fn ($query) => $query
                ->published()
                ->where(fn ($query) => $query
                    ->whereNull('active_from')
                    ->orWhere('active_from', '<=', today()))
                ->where(fn ($query) => $query
                    ->whereNull('active_until')
                    ->orWhere('active_until', '>=', today()))
                ->with('logo')])
            ->where('is_enabled', true)
            ->orderBy('position')
            ->get();
        $tierNodes = $this->all($document, $root, ".//*[contains(concat(' ', normalize-space(@class), ' '), ' sponsor-tier ')]");

        foreach ($tierNodes as $index => $node) {
            $tier = $tiers->get($index);
            if (! $tier) {
                $node->parentNode?->removeChild($node);

                continue;
            }

            $this->setFirstText($document, $node, './/h3[1]', $tier->name);
            $logoGrid = $this->first($document, $node, ".//*[contains(concat(' ', normalize-space(@class), ' '), ' logo-grid ')]");
            if ($logoGrid) {
                $this->replaceInner(
                    $document,
                    $logoGrid,
                    view('site.partials.sponsor-logos', ['sponsors' => $tier->sponsors])->render()
                );
            }
        }
    }

    private function hydrateNextGame(DOMDocument $document, DOMElement $root, ?Game $game): void
    {
        if (! $game) {
            return;
        }

        foreach (['next-game', 'schedule-next-game'] as $class) {
            $panel = $this->first(
                $document,
                $root,
                ".//*[contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')]"
            );
            if (! $panel) {
                continue;
            }

            $opponent = $game->opponent();
            $opponentName = $opponent?->name ?: 'Opponent TBD';
            $nameNode = $this->first(
                $document,
                $panel,
                $class === 'next-game'
                    ? ".//*[contains(concat(' ', normalize-space(@class), ' '), ' opponent ')]//strong"
                    : './/div[contains(@class, "schedule-matchup")]/div[2]//h3'
            );
            if ($nameNode) {
                $this->replaceText($document, $nameNode, $opponentName);
            }

            $details = $this->all($document, $panel, './/dl//dd');
            if (isset($details[0])) {
                $this->replaceText($document, $details[0], $game->starts_at->format('F j, Y'));
            }
            if (isset($details[1])) {
                $this->replaceText($document, $details[1], $game->starts_at->format('g:i A'));
            }
            if (isset($details[2])) {
                $this->replaceText($document, $details[2], $game->venue?->name ?: 'Venue to be announced');
            }

            $countdown = $this->first($document, $panel, './/*[@data-game-date]');
            $countdown?->setAttribute('data-game-date', $game->starts_at->toIso8601String());

            $ticket = $this->first($document, $panel, './/a[contains(@class, "schedule-ticket")]');
            if ($ticket && $game->ticket_url) {
                $ticket->setAttribute('href', $game->ticket_url);
                $ticket->setAttribute('target', '_blank');
                $ticket->setAttribute('rel', 'noopener noreferrer');
            }
        }
    }

    private function hydrateSeasonStats(DOMDocument $document, DOMElement $root, Season $season, $games): void
    {
        $finals = $games->filter(fn (Game $game) => $game->status === GameStatus::Final
            && $game->home_score !== null
            && $game->away_score !== null);
        $wins = 0;
        $pointsFor = 0;
        $pointsAgainst = 0;
        foreach ($finals as $game) {
            $teamScore = $game->isHomeGame() ? $game->home_score : $game->away_score;
            $opponentScore = $game->isHomeGame() ? $game->away_score : $game->home_score;
            $wins += $teamScore > $opponentScore ? 1 : 0;
            $pointsFor += $teamScore;
            $pointsAgainst += $opponentScore;
        }
        $losses = $finals->count() - $wins;
        $values = [
            $wins,
            $losses,
            $finals->count() ? ltrim(number_format($wins / $finals->count(), 3), '0') : '.000',
            $pointsFor,
            $pointsAgainst,
            sprintf('%+d', $pointsFor - $pointsAgainst),
        ];
        $nodes = $this->all(
            $document,
            $root,
            ".//*[contains(concat(' ', normalize-space(@class), ' '), ' season-stats ')]//article/strong"
        );
        foreach ($values as $index => $value) {
            if (isset($nodes[$index])) {
                $this->replaceText($document, $nodes[$index], (string) $value);
            }
        }
        $this->setFirstText(
            $document,
            $root,
            ".//*[contains(concat(' ', normalize-space(@class), ' '), ' season-stats ')]/h2",
            $season->name.' Stats'
        );
    }

    private function hydrateVenue(DOMDocument $document, DOMElement $root, $venue): void
    {
        if (! $venue) {
            return;
        }

        $panel = $this->first($document, $root, ".//*[contains(concat(' ', normalize-space(@class), ' '), ' home-venue ')]");
        if (! $panel) {
            return;
        }

        $imageNode = $this->first($document, $panel, ".//*[contains(concat(' ', normalize-space(@class), ' '), ' venue-image ')]");
        if ($imageNode && $venue->image) {
            $thumb = $venue->image->url('thumb') ?: $venue->image->url();
            $web = $venue->image->url('web') ?: $venue->image->url();
            $img = $document->createElement('img');
            $img->setAttribute('class', 'venue-image');
            $img->setAttribute('src', (string) $thumb);
            if ($thumb && $web) {
                $img->setAttribute('srcset', "{$thumb} 480w, {$web} 1600w");
                $img->setAttribute('sizes', '(max-width: 720px) 100vw, 50vw');
            }
            $img->setAttribute('alt', (string) $venue->image->alt_text);
            $img->setAttribute('loading', 'lazy');
            $img->setAttribute('decoding', 'async');
            $imageNode->parentNode?->replaceChild($img, $imageNode);
        }

        $this->setFirstText($document, $panel, ".//*[contains(concat(' ', normalize-space(@class), ' '), ' venue-address ')]//strong", $venue->name);
        $this->setFirstText($document, $panel, ".//*[contains(concat(' ', normalize-space(@class), ' '), ' venue-address ')]//span", $venue->formattedAddress());
        $facts = $this->all($document, $panel, ".//*[contains(concat(' ', normalize-space(@class), ' '), ' venue-facts ')]//strong");
        if (isset($facts[0])) {
            $this->replaceText($document, $facts[0], number_format($venue->capacity));
        }
        if (isset($facts[1])) {
            $this->replaceText($document, $facts[1], (string) $venue->opened_year);
        }
    }

    private function hydratePartnerLogos(DOMDocument $document, DOMElement $root, string $containerClass): void
    {
        $sponsors = SponsorTier::query()
            ->with(['sponsors' => fn ($query) => $query
                ->published()
                ->where(fn ($query) => $query
                    ->whereNull('active_from')
                    ->orWhere('active_from', '<=', today()))
                ->where(fn ($query) => $query
                    ->whereNull('active_until')
                    ->orWhere('active_until', '>=', today()))
                ->with('logo')])
            ->where('is_enabled', true)
            ->orderBy('position')
            ->get()
            ->flatMap->sponsors
            ->take(8);
        $container = $containerClass === 'schedule-partners'
            ? $this->first($document, $root, ".//*[contains(concat(' ', normalize-space(@class), ' '), ' schedule-partners ')]/div")
            : $this->first($document, $root, ".//*[contains(concat(' ', normalize-space(@class), ' '), ' {$containerClass} ')]");
        if ($container) {
            $container->setAttribute(
                'class',
                trim($container->getAttribute('class').' partner-carousel')
            );
            $container->setAttribute('data-partner-carousel', '');
            $container->setAttribute('role', 'region');
            $container->setAttribute('aria-roledescription', 'carousel');
            $container->setAttribute('aria-label', 'Proud partners of DMV Warriors');
            $this->replaceInner($document, $container, view('site.partials.partner-logos', compact('sponsors'))->render());
        }
    }

    private function replaceClass(DOMDocument $document, DOMElement $root, string $class, string $html): void
    {
        $node = $this->first(
            $document,
            $root,
            ".//*[contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')]"
        );
        if ($node) {
            $this->replaceInner($document, $node, $html);
        }
    }

    private function replaceInner(DOMDocument $document, DOMElement $target, string $html): void
    {
        while ($target->firstChild) {
            $target->removeChild($target->firstChild);
        }

        $fragment = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $fragment->loadHTML(
            '<?xml encoding="utf-8" ?><div id="fragment-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $fragmentRoot = $fragment->getElementById('fragment-root');
        foreach (iterator_to_array($fragmentRoot->childNodes) as $child) {
            $target->appendChild($document->importNode($child, true));
        }
    }

    private function setFirstText(DOMDocument $document, DOMElement $root, string $query, string $value): void
    {
        $node = $this->first($document, $root, $query);
        if ($node) {
            $this->replaceText($document, $node, $value);
        }
    }

    private function replaceText(DOMDocument $document, DOMElement $node, string $value): void
    {
        while ($node->firstChild) {
            $node->removeChild($node->firstChild);
        }
        $node->appendChild($document->createTextNode($value));
    }

    private function setLink(DOMDocument $document, DOMElement $root, string $query, string $url): void
    {
        $node = $this->first($document, $root, $query);
        $node?->setAttribute('href', $url);
    }

    private function first(DOMDocument $document, DOMElement $root, string $query): ?DOMElement
    {
        $node = (new DOMXPath($document))->query($query, $root)?->item(0);

        return $node instanceof DOMElement ? $node : null;
    }

    private function all(DOMDocument $document, DOMElement $root, string $query): array
    {
        return iterator_to_array((new DOMXPath($document))->query($query, $root) ?: []);
    }
}
