<?php

namespace App\Services;

use App\Domain\Content\PageSchemaRegistry;
use App\Domain\Content\SectionContentExtractor;
use App\Enums\MediaKind;
use App\Models\Category;
use App\Models\Game;
use App\Models\MediaAsset;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Person;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\RosterMembership;
use App\Models\Season;
use App\Models\SiteSetting;
use App\Models\SocialLink;
use App\Models\Sponsor;
use App\Models\SponsorTier;
use App\Models\StaffAssignment;
use App\Models\Standing;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use enshrined\svgSanitize\Sanitizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class StaticSiteImporter
{
    public function __construct(
        private readonly PageSchemaRegistry $registry,
        private readonly SectionContentExtractor $extractor
    ) {}

    public function run(bool $resetContent = false): array
    {
        if (
            ! $resetContent
            && SiteSetting::query()->where('key', 'migration.static_import_complete')->exists()
        ) {
            return $this->counts();
        }

        $media = $this->importMedia();

        DB::transaction(function () use ($media, $resetContent): void {
            $this->importAccessControl();
            $this->importSettings($media);
            $this->importNavigation($media);
            $this->importPages($media, $resetContent);
            $this->importCompetition($media);
            $this->importPeople();
            $this->importNews();
            $this->importSponsors($media);
            $this->importRedirects();
            $marker = SiteSetting::setValue(
                'migration.static_import_complete',
                true,
                'Static import complete',
                'migration',
                'boolean'
            );
            $marker->forceFill(['is_public' => false])->saveQuietly();
        });

        app(SiteChromeService::class)->forget();

        return $this->counts();
    }

    private function counts(): array
    {
        return [
            'pages' => Page::query()->count(),
            'sections' => PageSection::query()->count(),
            'media' => MediaAsset::query()->count(),
            'people' => Person::query()->count(),
            'games' => Game::query()->count(),
            'sponsors' => Sponsor::query()->count(),
        ];
    }

    private function importMedia(): array
    {
        $source = $this->resolveMediaSource();
        $files = collect(File::allFiles($source))->sortBy(fn ($file) => $file->getRelativePathname());
        $map = [];

        foreach ($files as $file) {
            $relative = 'assets/'.str_replace('\\', '/', $file->getRelativePathname());
            $kind = str_contains($relative, '/icons/') ? MediaKind::Icon : MediaKind::Image;
            $uuid = Uuid::uuid5(Uuid::NAMESPACE_URL, 'dmv-warriors://'.$relative)->toString();
            $asset = MediaAsset::query()->updateOrCreate(
                ['uuid' => $uuid],
                [
                    'kind' => $kind->value,
                    'title' => Str::of($file->getFilenameWithoutExtension())->replace(['-', '_'], ' ')->title(),
                    'alt_text' => $kind === MediaKind::Icon ? null : Str::of($file->getFilenameWithoutExtension())->replace(['-', '_'], ' ')->title(),
                    'is_decorative' => $kind === MediaKind::Icon,
                ]
            );

            if (! $asset->hasMedia('file')) {
                if (strtolower($file->getExtension()) === 'svg') {
                    $sanitizer = new Sanitizer;
                    $sanitized = $sanitizer->sanitize(file_get_contents($file->getPathname()));
                    if ($sanitized === false) {
                        throw new \RuntimeException("Could not sanitize {$relative}.");
                    }

                    $asset->addMediaFromString($sanitized)
                        ->usingFileName($file->getFilename())
                        ->toMediaCollection('file');
                } else {
                    $asset->addMedia($file->getPathname())
                        ->preservingOriginal()
                        ->usingFileName($file->getFilename())
                        ->toMediaCollection('file');
                }
            }

            $map[$relative] = $asset->id;
        }

        return $map;
    }

    private function resolveMediaSource(): string
    {
        $required = [
            'bmv_logo_transparent.png',
            'main_hero_banner.png',
            'icons',
        ];
        $sources = array_values(array_filter(
            config('cms.static_import_asset_sources', []),
            fn ($source) => is_string($source) && trim($source) !== ''
        ));

        foreach ($sources as $source) {
            $resolved = realpath($source);
            if (
                $resolved !== false
                && is_dir($resolved)
                && is_readable($resolved)
                && collect($required)->every(fn (string $path) => file_exists($resolved.DIRECTORY_SEPARATOR.$path))
                && File::allFiles($resolved) !== []
            ) {
                return $resolved;
            }
        }

        $checked = $sources === [] ? '(no paths configured)' : implode(', ', $sources);

        throw new \RuntimeException(
            'Static media import could not find a readable asset source. '
            .'Expected bmv_logo_transparent.png, main_hero_banner.png, and icons/ in one of: '
            .$checked
        );
    }

    private function importAccessControl(): void
    {
        $permissions = [
            'access admin',
            'manage pages',
            'review content',
            'publish content',
            'manage media',
            'manage roster',
            'manage schedule',
            'manage news',
            'manage sponsors',
            'manage submissions',
            'export submissions',
            'manage settings',
            'manage users',
            'view audit log',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $roles = [
            'Super Admin' => $permissions,
            'Publisher' => array_values(array_diff($permissions, ['manage users'])),
            'Editor' => [
                'access admin',
                'manage pages',
                'manage media',
                'manage roster',
                'manage schedule',
                'manage news',
                'manage sponsors',
            ],
            'Inbox Manager' => [
                'access admin',
                'manage submissions',
                'export submissions',
            ],
        ];

        foreach ($roles as $name => $rolePermissions) {
            Role::findOrCreate($name, 'web')->syncPermissions($rolePermissions);
        }

        $email = trim((string) env('CMS_ADMIN_EMAIL'));
        $password = (string) env('CMS_ADMIN_PASSWORD');
        $disallowedPasswords = ['', 'password', 'ChangeMe!2026'];

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('CMS_ADMIN_EMAIL must contain a valid administrator email.');
        }

        if (strlen($password) < 16 || in_array($password, $disallowedPasswords, true)) {
            throw new \RuntimeException(
                'CMS_ADMIN_PASSWORD must be a unique password of at least 16 characters.'
            );
        }

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => env('CMS_ADMIN_NAME', 'DMV Administrator'),
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );
        $user->assignRole('Super Admin');
    }

    private function importSettings(array $media): void
    {
        $settings = [
            ['branding.site_name', 'DMV Warriors Basketball', 'Site name', 'branding', 'text'],
            ['branding.logo_media_id', $media['assets/bmv_logo_transparent.png'] ?? null, 'Header logo', 'branding', 'media'],
            ['branding.footer_logo_media_id', $media['assets/bmv_logo_transparent.png'] ?? null, 'Footer logo', 'branding', 'media'],
            ['branding.default_og_media_id', $media['assets/main_hero_banner.png'] ?? null, 'Default social image', 'branding', 'media'],
            ['tickets.global_url', '/schedule', 'Global ticket URL', 'tickets', 'url'],
            ['tickets.button_label', 'Buy Tickets', 'Ticket button label', 'tickets', 'text'],
            ['contact.email', 'info@dmvwarriors.com', 'Public email', 'contact', 'email'],
            ['contact.notification_email', 'info@dmvwarriors.com', 'Notification email', 'contact', 'email'],
            ['contact.phone', '(301) 555-0198', 'Public phone', 'contact', 'text'],
            ['contact.address_short', "Prince George's Sports & Learning Complex, Landover, MD", 'Short address', 'contact', 'textarea'],
            ['contact.office_hours', "Monday - Friday: 9:00 AM - 5:00 PM\nSaturday: By Appointment\nSunday: Closed", 'Office hours', 'contact', 'textarea'],
            ['footer.marquee_text', 'DMV', 'Marquee text', 'footer', 'text'],
            ['footer.marquee_speed', 40, 'Marquee duration in seconds', 'footer', 'number'],
            ['footer.region_one', 'Washington D.C.', 'Region one', 'footer', 'text'],
            ['footer.region_two', 'Maryland', 'Region two', 'footer', 'text'],
            ['footer.region_three', 'Virginia', 'Region three', 'footer', 'text'],
            ['footer.motto', 'Built in the DMV. Earned on the Court.', 'Footer motto', 'footer', 'text'],
            ['footer.description', 'The DMV Warriors are more than a team. We are a movement. Built in the DMV and driven by discipline, teamwork and pride.', 'Footer description', 'footer', 'textarea'],
            ['footer.schedule_label', 'View 2026 Schedule', 'Schedule link label', 'footer', 'text'],
            ['footer.navigation_heading', 'Navigation', 'Navigation heading', 'footer', 'text'],
            ['footer.contact_heading', 'Contact', 'Contact heading', 'footer', 'text'],
            ['footer.social_heading', 'Follow Us', 'Social heading', 'footer', 'text'],
            ['footer.social_copy', 'Join the Warriors community.', 'Social copy', 'footer', 'text'],
            ['footer.copyright', 'DMV Warriors Basketball. All Rights Reserved.', 'Copyright text', 'footer', 'text'],
            ['footer.values', 'Discipline. Teamwork. Community. Excellence.', 'Footer values', 'footer', 'text'],
            ['footer.link_text', '', 'Footer link text', 'footer', 'text'],
            ['footer.link_url', '', 'Footer link URL', 'footer', 'url'],
            ['forms.newsletter_success', 'Thanks. You are on the game-day list.', 'Newsletter success message', 'forms', 'text'],
            ['forms.contact_success', 'Message received. The DMV Warriors team will contact you.', 'Contact success message', 'forms', 'text'],
            ['forms.sponsor_success', 'Thank you. Our partnership team will contact you.', 'Sponsor success message', 'forms', 'text'],
            ['forms.validation_required', 'Please complete this field.', 'Required field message', 'forms', 'text'],
            ['forms.validation_email', 'Enter a valid email address.', 'Invalid email message', 'forms', 'text'],
            ['forms.validation_rate_limit', 'Too many attempts. Please wait before trying again.', 'Rate limit message', 'forms', 'text'],
            ['forms.validation_human', 'Human verification failed. Please try again.', 'Human verification message', 'forms', 'text'],
        ];

        foreach ($settings as [$key, $value, $label, $group, $type]) {
            SiteSetting::setValue($key, $value, $label, $group, $type);
        }
    }

    private function importNavigation(array $media): void
    {
        $primary = [
            ['Home', '/', 10],
            ['About Us', '/about', 20],
            ['Roster', '/roster', 30],
            ['Schedule', '/schedule', 40],
            ['Sponsors', '/sponsors', 50],
            ['Gallery', '/gallery', 55],
            ['Contact', '/contact', 60],
        ];
        $footer = [
            ['Home', '/', 10],
            ['About Us', '/about', 20],
            ['Roster', '/roster', 30],
            ['Schedule', '/schedule', 40],
            ['Sponsors', '/sponsors', 50],
            ['Gallery', '/gallery', 55],
            ['Contact', '/contact', 60],
            ['Policies', '/#footer', 70],
        ];

        foreach (['primary' => $primary, 'footer' => $footer] as $location => $items) {
            foreach ($items as [$label, $url, $position]) {
                NavigationItem::withTrashed()->updateOrCreate(
                    compact('location', 'label'),
                    compact('url', 'position') + ['is_enabled' => true]
                );
            }
        }

        $socials = [
            ['instagram', 'Instagram', '#', 'instagram.svg', 10],
            ['facebook', 'Facebook', '#', 'facebook.svg', 20],
            ['twitter', 'X', '#', 'twitter.svg', 30],
            ['youtube', 'YouTube', '#', 'youtube.svg', 40],
            ['tiktok', 'TikTok', '#', 'tiktok.svg', 50],
        ];

        foreach ($socials as [$platform, $label, $url, $icon, $position]) {
            SocialLink::withTrashed()->updateOrCreate(
                compact('platform'),
                compact('label', 'url', 'position') + [
                    'icon_media_id' => $media['assets/icons/'.$icon] ?? null,
                    'is_enabled' => true,
                ]
            );
        }
    }

    private function importPages(array $media, bool $resetContent): void
    {
        $descriptions = [
            'home' => 'DMV Warriors basketball club home page.',
            'about' => 'Learn about the DMV Warriors mission, leadership, values, and community impact.',
            'roster' => 'Meet the DMV Warriors players and coaching staff.',
            'schedule' => 'View DMV Warriors games, standings, venue information, and downloadable schedule.',
            'sponsors' => 'Meet DMV Warriors sponsors and explore partnership opportunities.',
            'contact' => 'Contact the DMV Warriors team.',
        ];

        foreach ($this->registry->all() as $templateKey => $schema) {
            $page = Page::query()->updateOrCreate(
                ['slug' => $templateKey],
                [
                    'template_key' => $templateKey,
                    'title' => $schema['title'],
                    'status' => 'published',
                    'seo_title' => $schema['title'].' | DMV Warriors',
                    'seo_description' => $descriptions[$templateKey],
                    'published_at' => now(),
                ]
            );
            $html = file_get_contents($this->registry->templatePath($templateKey));

            foreach ($schema['sections'] as $sectionKey => $sectionSchema) {
                $extracted = $this->extractor->extract(
                    $html,
                    $sectionSchema['selector'],
                    function (string $path) use ($media): ?int {
                        $normalized = ltrim(str_replace('\\', '/', $path), '/');

                        return $media[$normalized] ?? null;
                    }
                );
                $section = $page->sections()->where('section_key', $sectionKey)->first();
                $payload = $section && ! $resetContent
                    ? array_replace($extracted['payload'], $section->payload)
                    : $extracted['payload'];

                $page->sections()->updateOrCreate(
                    ['section_key' => $sectionKey],
                    [
                        'label' => $sectionSchema['label'],
                        'position' => array_search($sectionKey, array_keys($schema['sections']), true) + 1,
                        'schema_version' => 1,
                        'is_enabled' => $section?->is_enabled ?? true,
                        'field_schema' => $extracted['schema'],
                        'payload' => $payload,
                    ]
                );
            }
        }
    }

    private function importCompetition(array $media): void
    {
        $season = Season::withTrashed()->updateOrCreate(
            ['slug' => '2026-season'],
            [
                'name' => '2026 Season',
                'starts_on' => '2026-05-01',
                'ends_on' => '2026-11-30',
                'is_current' => true,
                'status' => 'published',
                'workflow_status' => 'published',
                'published_at' => now(),
            ]
        );

        $teamRows = [
            ['DMV Warriors', 'dmv-warriors', 'DMV', true, '#c9252d'],
            ['Virginia Elite', 'virginia-elite', 'VE', false, '#173f7a'],
            ['Baltimore Storm', 'baltimore-storm', 'BS', false, '#31343a'],
            ['DMV Dragons', 'dmv-dragons', 'DD', false, '#9f1d23'],
            ['Maryland Bulls', 'maryland-bulls', 'MB', false, '#642323'],
            ['Northern Kings', 'northern-kings', 'NK', false, '#5a4a16'],
            ['DC Panthers', 'dc-panthers', 'DP', false, '#302d62'],
            ['Capital City Kings', 'capital-city-kings', 'CC', false, '#2b3440'],
        ];
        $teams = [];

        foreach ($teamRows as [$name, $slug, $abbreviation, $home, $color]) {
            $teams[$slug] = Team::withTrashed()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'short_name' => $name,
                    'abbreviation' => $abbreviation,
                    'primary_color' => $color,
                    'logo_media_id' => $home ? ($media['assets/bmv_logo_transparent.png'] ?? null) : null,
                    'is_home_team' => $home,
                    'status' => 'published',
                    'workflow_status' => 'published',
                    'published_at' => now(),
                ]
            );
        }

        $venue = Venue::withTrashed()->updateOrCreate(
            ['slug' => 'prince-georges-sports-learning-complex'],
            [
                'name' => "Prince George's Sports & Learning Complex",
                'address_line_1' => '8001 Sheriff Rd',
                'city' => 'Landover',
                'state' => 'MD',
                'postal_code' => '20785',
                'capacity' => 3500,
                'opened_year' => 2018,
                'amenities' => ['Free parking', 'Concessions available'],
                'directions_url' => 'https://www.google.com/maps/search/?api=1&query=Prince+George%27s+Sports+and+Learning+Complex',
                'status' => 'published',
                'workflow_status' => 'published',
                'published_at' => now(),
            ]
        );

        $games = [
            ['2026-06-07 18:00', 'dmv-warriors', 'virginia-elite', 92, 78, 'final'],
            ['2026-06-11 19:00', 'baltimore-storm', 'dmv-warriors', 88, 81, 'final'],
            ['2026-06-14 18:00', 'dmv-warriors', 'dmv-dragons', 95, 91, 'final'],
            ['2026-06-18 19:00', 'maryland-bulls', 'dmv-warriors', 82, 104, 'final'],
            ['2026-06-21 19:00', 'dmv-warriors', 'northern-kings', 73, 77, 'final'],
            ['2026-06-25 19:00', 'dc-panthers', 'dmv-warriors', 85, 98, 'final'],
            ['2026-08-22 19:00', 'capital-city-kings', 'dmv-warriors', null, null, 'scheduled'],
            ['2026-10-10 19:00', 'capital-city-kings', 'dmv-warriors', null, null, 'scheduled'],
            ['2026-10-17 18:00', 'dmv-warriors', 'virginia-elite', null, null, 'scheduled'],
            ['2026-10-25 19:00', 'baltimore-storm', 'dmv-warriors', null, null, 'scheduled'],
        ];

        foreach ($games as $index => [$date, $away, $home, $awayScore, $homeScore, $status]) {
            $startsAt = CarbonImmutable::parse($date, 'America/New_York');
            $slug = $startsAt->format('Y-m-d').'-'.$away.'-at-'.$home;
            Game::withTrashed()->updateOrCreate(
                ['slug' => $slug],
                [
                    'season_id' => $season->id,
                    'home_team_id' => $teams[$home]->id,
                    'away_team_id' => $teams[$away]->id,
                    'venue_id' => $venue->id,
                    'starts_at' => $startsAt,
                    'timezone' => 'America/New_York',
                    'status' => $status,
                    'home_score' => $homeScore,
                    'away_score' => $awayScore,
                    'ticket_url' => $status === 'scheduled' ? 'https://www.ticketmaster.com/' : null,
                    'is_featured' => $index === 6,
                    'publication_status' => 'published',
                    'workflow_status' => 'published',
                    'published_at' => now(),
                ]
            );
        }

        $standings = [
            ['dmv-warriors', 1, 8, 2],
            ['capital-city-kings', 2, 7, 3],
            ['virginia-elite', 3, 6, 4],
            ['baltimore-storm', 4, 5, 5],
            ['dmv-dragons', 5, 4, 6],
        ];
        foreach ($standings as [$team, $rank, $wins, $losses]) {
            Standing::withTrashed()->updateOrCreate(
                ['season_id' => $season->id, 'team_id' => $teams[$team]->id, 'division' => 'Mid-Atlantic'],
                [
                    'rank' => $rank,
                    'wins' => $wins,
                    'losses' => $losses,
                    'win_percentage' => $wins / max(1, $wins + $losses),
                    'position_order' => $rank,
                ]
            );
        }
    }

    private function importPeople(): void
    {
        $season = Season::query()->where('is_current', true)->firstOrFail();
        $players = [
            ['Jaylen', 'Parker', '23', 'Guard', "6'2\"", 'Senior'],
            ['Marcus', 'Johnson', '5', 'Guard', "6'1\"", 'Senior'],
            ['Darius', 'Williams', '12', 'Forward', "6'7\"", 'Senior'],
            ['Isaiah', 'Roberts', '32', 'Center', "6'9\"", 'Senior'],
            ['Brandon', 'Reed', '11', 'Guard', "6'3\"", 'Junior'],
            ['Tyler', 'Harris', '3', 'Guard', "6'2\"", 'Junior'],
            ['Jordan', 'Baker', '15', 'Forward', "6'6\"", 'Sophomore'],
            ['Anthony', 'Moore', '24', 'Forward', "6'6\"", 'Junior'],
            ['Cameron', 'Price', '1', 'Guard', "6'0\"", 'Freshman'],
            ['Malik', 'Thompson', '21', 'Center', "6'10\"", 'Junior'],
            ['Devin', 'Jackson', '2', 'Guard', "6'1\"", 'Sophomore'],
            ['Caleb', 'Davis', '34', 'Forward', "6'7\"", 'Freshman'],
        ];

        foreach ($players as $index => [$first, $last, $number, $position, $height, $class]) {
            $person = Person::withTrashed()->updateOrCreate(
                ['slug' => Str::slug($first.' '.$last)],
                [
                    'type' => 'player',
                    'first_name' => $first,
                    'last_name' => $last,
                    'display_name' => $first.' '.$last,
                    'status' => 'published',
                    'workflow_status' => 'published',
                    'published_at' => now(),
                ]
            );
            RosterMembership::withTrashed()->updateOrCreate(
                ['season_id' => $season->id, 'person_id' => $person->id],
                [
                    'jersey_number' => $number,
                    'position' => $position,
                    'height' => $height,
                    'class_year' => $class,
                    'position_order' => $index + 1,
                    'is_featured' => $index < 4,
                    'is_active' => true,
                ]
            );
        }

        $staff = [
            ['Marcus', 'Hill', 'Head Coach', true, 'Former collegiate player with 15+ years of coaching experience. Dedicated to building champions on and off the court.'],
            ['James', 'Carter', 'Assistant Coach', true, 'Specializes in player development and game strategy. Passionate about mentoring young athletes.'],
            ['Derrick', 'Moore', 'Player Development', true, 'Focuses on skills training and athlete performance. Committed to helping every player improve.'],
            ['Tyler', 'Washington', 'Strength Coach', false, 'Expert in strength and conditioning. Builds the physical and mental toughness of our team.'],
        ];

        foreach ($staff as $index => [$first, $last, $role, $leadership, $bio]) {
            $person = Person::withTrashed()->updateOrCreate(
                ['slug' => Str::slug('coach '.$first.' '.$last)],
                [
                    'type' => 'staff',
                    'first_name' => $first,
                    'last_name' => $last,
                    'display_name' => $first.' '.$last,
                    'biography' => $bio,
                    'status' => 'published',
                    'workflow_status' => 'published',
                    'published_at' => now(),
                ]
            );
            StaffAssignment::withTrashed()->updateOrCreate(
                ['season_id' => $season->id, 'person_id' => $person->id, 'role' => $role],
                [
                    'position_order' => $index + 1,
                    'is_leadership' => $leadership,
                    'is_active' => true,
                ]
            );
        }
    }

    private function importNews(): void
    {
        $category = Category::withTrashed()->updateOrCreate(
            ['slug' => 'team-news'],
            ['name' => 'Team News', 'description' => 'DMV Warriors announcements and community stories.']
        );
        $posts = [
            ['Tryouts Are Open!', 'tryouts-are-open', 'DMV Warriors open tryouts for the upcoming season. Register today and show your talent.', '2026-05-20'],
            ['Community Basketball Camp', 'community-basketball-camp', "We're hosting a free youth basketball camp this summer. Building skills. Building leaders.", '2026-05-15'],
            ['Welcome Our New Partner', 'welcome-our-new-partner', 'Proud to partner with local businesses that support our mission and the DMV community.', '2026-05-10'],
        ];

        foreach ($posts as $index => [$title, $slug, $excerpt, $date]) {
            Post::withTrashed()->updateOrCreate(
                ['slug' => $slug],
                [
                    'category_id' => $category->id,
                    'title' => $title,
                    'excerpt' => $excerpt,
                    'body' => $excerpt,
                    'status' => 'published',
                    'workflow_status' => 'published',
                    'is_featured' => $index === 0,
                    'published_at' => CarbonImmutable::parse($date),
                ]
            );
        }
    }

    private function importSponsors(array $media): void
    {
        $tiers = [
            ['Title Sponsor', 'title-sponsor', 10],
            ['Platinum Sponsors', 'platinum-sponsors', 20],
            ['Gold Sponsors', 'gold-sponsors', 30],
            ['Community Partners', 'community-partners', 40],
        ];
        $tierModels = [];
        foreach ($tiers as [$name, $slug, $position]) {
            $tierModels[$slug] = SponsorTier::withTrashed()->updateOrCreate(
                ['slug' => $slug],
                compact('name', 'position') + ['is_enabled' => true]
            );
        }

        $sponsors = [
            ['Nike', 'nike', 'title-sponsor', 'nike.svg', 10],
            ['Gatorade', 'gatorade', 'platinum-sponsors', 'gatorade.svg', 10],
            ['Wilson', 'wilson', 'platinum-sponsors', 'wilson.svg', 20],
            ["Dick's Sporting Goods", 'dicks-sporting-goods', 'platinum-sponsors', 'dicks-sporting-goods.svg', 30],
            ['Molten', 'molten', 'platinum-sponsors', 'molten.svg', 40],
            ['Chick-fil-A', 'chick-fil-a', 'platinum-sponsors', 'chick-fil-a.svg', 50],
            ['Spalding', 'spalding', 'gold-sponsors', 'spalding.svg', 10],
            ['Ticketmaster', 'ticketmaster', 'gold-sponsors', 'ticketmaster.svg', 20],
            ['NBA', 'nba', 'community-partners', 'nba.svg', 10],
        ];

        foreach ($sponsors as [$name, $slug, $tier, $logo, $position]) {
            Sponsor::withTrashed()->updateOrCreate(
                ['slug' => $slug],
                [
                    'sponsor_tier_id' => $tierModels[$tier]->id,
                    'name' => $name,
                    'logo_media_id' => $media['assets/partners/'.$logo] ?? null,
                    'position' => $position,
                    'is_featured' => $tier === 'title-sponsor',
                    'status' => 'published',
                    'workflow_status' => 'published',
                    'published_at' => now(),
                ]
            );
        }
    }

    private function importRedirects(): void
    {
        foreach (config('cms.legacy_redirects', []) as $source => $destination) {
            Redirect::withTrashed()->updateOrCreate(
                ['source_path' => $source],
                ['destination_url' => $destination, 'status_code' => 301, 'is_enabled' => true]
            );
        }
    }
}
