<?php

namespace Tests\Feature;

use App\Enums\MediaKind;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\Venue;
use App\Services\StaticSiteImporter;
use App\Services\SiteChromeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_imported_public_site_routes_metadata_redirects_and_downloads_work(): void
    {
        $counts = app(StaticSiteImporter::class)->run();

        foreach (['/', '/about', '/roster', '/schedule', '/sponsors', '/contact', '/news'] as $uri) {
            $response = $this->get($uri)
                ->assertOk()
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

            $this->assertStringNotContainsString('src=""', $response->getContent());
        }

        $this->get('/about.html')->assertRedirect('/about')->assertStatus(301);
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
            ->assertSee('/about');
        $this->get('/schedule/calendar.ics')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->assertSee('BEGIN:VCALENDAR')
            ->assertSee('BEGIN:VEVENT');
        $this->get('/sponsor-pack')
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename=dmv-warriors-sponsor-pack.pdf')
            ->assertHeader('Content-Type', 'application/pdf');
        $this->get('/')
            ->assertSee('data-partner-carousel', false)
            ->assertSee('data-partner-previous', false)
            ->assertSee('partner-carousel-slide', false);

        $this->assertSame(6, $counts['pages']);
        $this->assertSame(40, $counts['sections']);
    }

    public function test_schedule_venue_image_falls_back_to_a_placeholder_and_renders_when_assigned(): void
    {
        app(StaticSiteImporter::class)->run();

        $withoutImage = $this->get('/schedule')->assertOk()->getContent();
        $this->assertStringContainsString('class="venue-image media-placeholder"', $withoutImage);
        $this->assertStringNotContainsString('<img class="venue-image"', $withoutImage);

        Storage::fake('public');
        $asset = MediaAsset::query()->create([
            'kind' => MediaKind::Image->value,
            'title' => 'Venue photo',
            'alt_text' => 'Prince George\'s Sports & Learning Complex',
        ]);
        $asset->addMediaFromString('fake-image-bytes')->usingFileName('venue.jpg')->toMediaCollection('file');
        Venue::query()
            ->where('slug', 'prince-georges-sports-learning-complex')
            ->update(['image_media_id' => $asset->id]);

        $withImage = $this->get('/schedule')->assertOk()->getContent();
        $this->assertStringContainsString('<img class="venue-image"', $withImage);
        $this->assertStringContainsString($asset->url('thumb') ?: $asset->url(), $withImage);
    }

    public function test_static_import_is_idempotent(): void
    {
        $first = app(StaticSiteImporter::class)->run();
        Page::query()->where('slug', 'home')->update(['title' => 'Editor title']);
        SiteSetting::query()->where('key', 'footer.motto')->update([
            'value' => ['value' => 'Editor motto'],
        ]);
        $second = app(StaticSiteImporter::class)->run();

        $this->assertSame($first, $second);
        $this->assertSame('Editor title', Page::query()->where('slug', 'home')->value('title'));
        $this->assertSame('Editor motto', SiteSetting::value('footer.motto'));
    }

    public function test_footer_renders_the_structured_link_without_allowing_raw_html(): void
    {
        app(StaticSiteImporter::class)->run();
        SiteSetting::query()->where('key', 'footer.values')->update([
            'value' => ['value' => 'Designed by <strong>'],
        ]);
        SiteSetting::query()->where('key', 'footer.link_text')->update([
            'value' => ['value' => 'SAPCO Technologies'],
        ]);
        SiteSetting::query()->where('key', 'footer.link_url')->update([
            'value' => ['value' => 'https://sapcotechnologies.com'],
        ]);
        app(SiteChromeService::class)->forget();

        $this->get('/')
            ->assertOk()
            ->assertSee('Designed by &lt;strong&gt;', false)
            ->assertSee('href="https://sapcotechnologies.com"', false)
            ->assertSee('SAPCO Technologies');
    }

    public function test_footer_values_become_clickable_when_link_text_is_blank(): void
    {
        app(StaticSiteImporter::class)->run();
        SiteSetting::query()->where('key', 'footer.values')->update([
            'value' => ['value' => 'Website by SAPCO'],
        ]);
        SiteSetting::query()->where('key', 'footer.link_text')->update([
            'value' => ['value' => ''],
        ]);
        SiteSetting::query()->where('key', 'footer.link_url')->update([
            'value' => ['value' => 'https://sapcotechnologies.com'],
        ]);
        app(SiteChromeService::class)->forget();

        $this->get('/')
            ->assertOk()
            ->assertSee(
                'href="https://sapcotechnologies.com"',
                false
            )
            ->assertSee('Website by SAPCO');
    }
}
