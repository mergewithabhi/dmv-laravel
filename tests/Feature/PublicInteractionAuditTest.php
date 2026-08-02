<?php

namespace Tests\Feature;

use App\Jobs\SyncNewsletterSubscriber;
use App\Livewire\Site\NewsIndex;
use App\Livewire\Site\SitePage;
use App\Models\FormSubmission;
use App\Models\Game;
use App\Models\NewsletterSubscriber;
use App\Models\Person;
use App\Models\Post;
use App\Models\RosterMembership;
use App\Models\StaffAssignment;
use App\Services\StaticSiteImporter;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class PublicInteractionAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'http://localhost/dmv-laravel']);
        URL::forceRootUrl('http://localhost/dmv-laravel');
        app(StaticSiteImporter::class)->run();
    }

    public function test_public_navigation_and_ctas_stay_inside_the_xampp_alias(): void
    {
        foreach (['/', '/about', '/roster', '/schedule', '/sponsors', '/contact', '/news'] as $uri) {
            $response = $this->get('http://localhost'.$uri)->assertOk();
            $anchors = $this->anchors($response->getContent());

            foreach ($anchors as $anchor) {
                $href = $anchor->getAttribute('href');
                $this->assertFalse(
                    str_starts_with($href, '/') && ! str_starts_with($href, '//'),
                    "{$uri} contains an alias-breaking root-relative link: {$href}"
                );

                if (
                    str_starts_with($href, 'http://localhost/dmv-laravel')
                    && ! $anchor->hasAttribute('download')
                    && $anchor->getAttribute('target') !== '_blank'
                    && ! str_contains($href, '/schedule/calendar.ics')
                    && ! str_ends_with($href, '/sponsor-pack')
                ) {
                    $this->assertTrue(
                        $anchor->hasAttribute('wire:navigate'),
                        "{$uri} internal link is missing wire:navigate: {$href}"
                    );
                }
            }
        }
    }

    public function test_all_public_controls_have_functional_server_or_javascript_targets(): void
    {
        $home = $this->get('http://localhost/')->assertOk();
        $home->assertSee('aria-controls="primary-navigation"', false)
            ->assertSee('data-partner-carousel', false)
            ->assertSee('data-partner-previous', false)
            ->assertSee('data-partner-next', false)
            ->assertSee('wire:submit="submitNewsletter"', false)
            ->assertSee('wire:model="newsletterConsent"', false)
            ->assertSee('wire:loading.attr="disabled"', false);

        $schedule = $this->get('http://localhost/schedule')->assertOk();
        $schedule->assertSee('class="calendar-nav calendar-prev"', false)
            ->assertSee('class="calendar-nav calendar-next"', false)
            ->assertSee('data-schedule-filter="all"', false)
            ->assertSee('data-schedule-filter="home"', false)
            ->assertSee('data-schedule-filter="away"', false)
            ->assertSee('data-schedule-month', false)
            ->assertSee('data-schedule-reset', false)
            ->assertSee('href="http://localhost/dmv-laravel/schedule/calendar.ics"', false)
            ->assertSee('download=""', false)
            ->assertDontSee('data-download-schedule', false);

        $sponsors = $this->get('http://localhost/sponsors')->assertOk();
        $sponsors->assertSee('wire:submit="submitSponsor"', false)
            ->assertSee('wire:model="sponsorConsent"', false)
            ->assertSee('href="http://localhost/dmv-laravel/sponsor-pack"', false)
            ->assertDontSee('data-download-sponsor', false);

        $this->get('http://localhost/contact')
            ->assertOk()
            ->assertSee('wire:submit="submitContact"', false)
            ->assertSee('wire:model="contactConsent"', false)
            ->assertSee('https://www.google.com/maps/search/', false);

        config([
            'services.turnstile.enabled' => true,
            'services.turnstile.site_key' => 'public-test-site-key',
        ]);
        $this->get('http://localhost/contact')
            ->assertOk()
            ->assertSee(
                'src="https://challenges.cloudflare.com/turnstile/v0/api.js?onload=DMVTurnstileLoaded&render=explicit" defer',
                false
            )
            ->assertDontSee('defer onload=', false);

        $this->get('http://localhost/schedule/calendar.ics')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $this->get('http://localhost/sponsor-pack')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_news_player_and_game_interactions_and_news_pagination_work(): void
    {
        $post = Post::query()->published()->firstOrFail();
        $person = Person::query()->where('type', 'player')->published()->firstOrFail();
        $game = Game::query()->published()->firstOrFail();

        $this->get('http://localhost/news/'.$post->slug)
            ->assertOk()
            ->assertSee('href="'.route('news.index').'" wire:navigate', false)
            ->assertDontSee('Â·', false);
        $this->get('http://localhost/players/'.$person->slug)
            ->assertOk()
            ->assertSee('href="'.route('site.page', ['slug' => 'roster']).'" wire:navigate', false)
            ->assertDontSee('href="/roster"', false)
            ->assertDontSee('Â·', false);
        $this->get('http://localhost/games/'.$game->slug)
            ->assertOk()
            ->assertSee('href="'.route('site.page', ['slug' => 'schedule']).'" wire:navigate', false)
            ->assertDontSee('href="/schedule"', false)
            ->assertDontSee('Â·', false);

        for ($index = 1; $index <= 10; $index++) {
            $copy = $post->replicate();
            $copy->title = "Interaction audit story {$index}";
            $copy->slug = "interaction-audit-story-{$index}";
            $copy->published_at = now()->subMinutes($index);
            $copy->save();
        }

        config(['app.url' => 'http://localhost']);
        URL::forceRootUrl('http://localhost');

        Livewire::test(NewsIndex::class)
            ->assertSee('Interaction audit story 1')
            ->call('gotoPage', 2)
            ->assertSet('paginators.page', 2)
            ->assertSee($post->title);
    }

    public function test_person_social_links_are_accessible_and_unsafe_urls_are_not_rendered(): void
    {
        $membership = RosterMembership::query()
            ->with('person')
            ->where('is_active', true)
            ->where('is_featured', true)
            ->firstOrFail();
        $membership->person->update([
            'social_links' => [
                'instagram' => 'https://instagram.com/dmv-test-player',
                [
                    'platform' => 'X',
                    'label' => 'X',
                    'url' => 'https://x.com/dmv-test-player',
                ],
                [
                    'platform' => 'Unsafe',
                    'url' => 'javascript:alert(1)',
                ],
            ],
        ]);

        $assignment = StaffAssignment::query()
            ->with('person')
            ->where('is_active', true)
            ->where('is_leadership', true)
            ->firstOrFail();
        $assignment->person->update([
            'social_links' => [
                [
                    'platform' => 'Facebook',
                    'url' => 'https://facebook.com/dmv-test-coach',
                ],
                [
                    'platform' => 'Unsafe',
                    'url' => 'data:text/html,bad',
                ],
            ],
        ]);

        foreach (['/', '/roster', '/players/'.$membership->person->slug] as $uri) {
            $this->get('http://localhost'.$uri)
                ->assertOk()
                ->assertSee('href="https://instagram.com/dmv-test-player"', false)
                ->assertSee('target="_blank" rel="noopener noreferrer"', false)
                ->assertSee(
                    'aria-label="'.$membership->person->display_name.' on Instagram"',
                    false
                )
                ->assertDontSee('javascript:alert(1)', false);
        }

        foreach (['/about', '/roster'] as $uri) {
            $this->get('http://localhost'.$uri)
                ->assertOk()
                ->assertSee('href="https://facebook.com/dmv-test-coach"', false)
                ->assertSee(
                    'aria-label="'.$assignment->person->display_name.' on Facebook"',
                    false
                )
                ->assertDontSee('data:text/html,bad', false);
        }
    }

    public function test_public_forms_validate_submit_once_and_persist_their_data(): void
    {
        Notification::fake();
        Queue::fake();
        config(['app.url' => 'http://localhost']);
        URL::forceRootUrl('http://localhost');

        Livewire::test(SitePage::class, ['slug' => 'home'])
            ->call('submitNewsletter')
            ->assertHasErrors('newsletterEmail')
            ->set('newsletterEmail', 'fan@gmail.com')
            ->set('newsletterConsent', true)
            ->call('submitNewsletter')
            ->assertHasNoErrors()
            ->assertSet('newsletterConsent', false)
            ->assertDispatched('site-form-complete');

        Livewire::test(SitePage::class, ['slug' => 'contact'])
            ->set('contact.name', 'Public Interaction Audit')
            ->set('contact.email', 'visitor@gmail.com')
            ->set('contact.subject', 'Tickets')
            ->set('contact.message', 'Please send ticket information.')
            ->set('contactConsent', true)
            ->call('submitContact')
            ->assertHasNoErrors()
            ->assertSet('contactConsent', false)
            ->assertDispatched('site-form-complete');

        Livewire::test(SitePage::class, ['slug' => 'sponsors'])
            ->set('sponsor.name', 'Partner Contact')
            ->set('sponsor.company', 'DMV Test Partner')
            ->set('sponsor.email', 'partner@gmail.com')
            ->set('sponsor.phone', '301-555-0100')
            ->set('sponsor.level', 'Gold Sponsor')
            ->set('sponsor.message', 'Please send the partnership details.')
            ->set('sponsorConsent', true)
            ->call('submitSponsor')
            ->assertHasNoErrors()
            ->assertSet('sponsorConsent', false)
            ->assertDispatched('site-form-complete');

        $this->assertSame(1, NewsletterSubscriber::query()->count());
        $this->assertSame(1, FormSubmission::query()->where('type', 'contact')->count());
        $this->assertSame(1, FormSubmission::query()->where('type', 'sponsor')->count());
        Queue::assertPushed(SyncNewsletterSubscriber::class);
    }

    /**
     * @return list<DOMElement>
     */
    private function anchors(string $html): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return array_values(array_filter(
            iterator_to_array((new DOMXPath($document))->query('//a[@href]') ?: []),
            fn ($node): bool => $node instanceof DOMElement
        ));
    }
}
