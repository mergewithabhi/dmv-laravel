<?php

namespace App\Domain\Admin;

use App\Models\Category;
use App\Models\Game;
use App\Models\MediaAsset;
use App\Models\NavigationItem;
use App\Models\Person;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\RosterMembership;
use App\Models\Season;
use App\Models\SocialLink;
use App\Models\Sponsor;
use App\Models\SponsorTier;
use App\Models\StaffAssignment;
use App\Models\Standing;
use App\Models\Team;
use App\Models\Venue;

class ResourceRegistry
{
    public function all(): array
    {
        return [
            'people' => [
                'label' => 'People',
                'model' => Person::class,
                'permission' => 'manage roster',
                'search' => ['display_name', 'first_name', 'last_name', 'type'],
                'with' => ['photo'],
                'columns' => ['display_name' => 'Name', 'type' => 'Type', 'workflow_status' => 'Status'],
                'sort' => 'display_name',
                'status_field' => 'status',
                'workflow' => true,
                'fields' => [
                    'type' => $this->field('Type', 'select', 'required', 'person_types'),
                    'first_name' => $this->field('First name', 'text', 'required|max:120'),
                    'last_name' => $this->field('Last name', 'text', 'required|max:120'),
                    'display_name' => $this->field('Display name', 'text', 'required|max:180'),
                    'slug' => $this->field('Slug', 'text', 'required|max:180'),
                    'hometown' => $this->field('Hometown', 'text', 'nullable|max:180'),
                    'biography' => $this->field('Biography', 'textarea', 'nullable|max:10000', full: true),
                    'photo_media_id' => $this->field('Photo', 'select', 'nullable|exists:media_assets,id', 'media_images'),
                    'social_links' => $this->field('Social links', 'json', 'nullable|json', full: true),
                    'statistics' => $this->field('Player statistics', 'json', 'nullable|json', full: true),
                    'status' => $this->field('Publication status', 'select', 'required|in:draft,scheduled,published,archived', 'publication_statuses'),
                    'publish_at' => $this->field('Publish at', 'datetime-local', 'nullable|date'),
                ],
            ],
            'seasons' => [
                'label' => 'Seasons',
                'model' => Season::class,
                'permission' => 'manage schedule',
                'search' => ['name', 'slug'],
                'columns' => ['name' => 'Season', 'starts_on' => 'Starts', 'ends_on' => 'Ends', 'workflow_status' => 'Status'],
                'sort' => 'starts_on',
                'status_field' => 'status',
                'workflow' => true,
                'fields' => [
                    'name' => $this->field('Name', 'text', 'required|max:120'),
                    'slug' => $this->field('Slug', 'text', 'required|max:160'),
                    'starts_on' => $this->field('Starts on', 'date', 'required|date'),
                    'ends_on' => $this->field('Ends on', 'date', 'required|date|after_or_equal:form.starts_on'),
                    'is_current' => $this->field('Current season', 'checkbox', 'boolean'),
                    'status' => $this->field('Publication status', 'select', 'required|in:draft,scheduled,published,archived', 'publication_statuses'),
                    'publish_at' => $this->field('Publish at', 'datetime-local', 'nullable|date'),
                ],
            ],
            'teams' => [
                'label' => 'Teams',
                'model' => Team::class,
                'permission' => 'manage schedule',
                'search' => ['name', 'short_name', 'abbreviation'],
                'with' => ['logo'],
                'columns' => ['name' => 'Team', 'abbreviation' => 'Code', 'is_home_team' => 'DMV team', 'workflow_status' => 'Status'],
                'sort' => 'name',
                'status_field' => 'status',
                'workflow' => true,
                'fields' => [
                    'name' => $this->field('Name', 'text', 'required|max:180'),
                    'short_name' => $this->field('Short name', 'text', 'nullable|max:100'),
                    'slug' => $this->field('Slug', 'text', 'required|max:180'),
                    'abbreviation' => $this->field('Abbreviation', 'text', 'nullable|max:8'),
                    'primary_color' => $this->field('Primary color', 'text', 'nullable|max:16'),
                    'secondary_color' => $this->field('Secondary color', 'text', 'nullable|max:16'),
                    'logo_media_id' => $this->field('Logo', 'select', 'nullable|exists:media_assets,id', 'media_images'),
                    'is_home_team' => $this->field('DMV Warriors team', 'checkbox', 'boolean'),
                    'status' => $this->field('Publication status', 'select', 'required|in:draft,scheduled,published,archived', 'publication_statuses'),
                    'publish_at' => $this->field('Publish at', 'datetime-local', 'nullable|date'),
                ],
            ],
            'venues' => [
                'label' => 'Venues',
                'model' => Venue::class,
                'permission' => 'manage schedule',
                'search' => ['name', 'city', 'state'],
                'columns' => ['name' => 'Venue', 'city' => 'City', 'state' => 'State', 'workflow_status' => 'Status'],
                'sort' => 'name',
                'status_field' => 'status',
                'workflow' => true,
                'fields' => [
                    'name' => $this->field('Name', 'text', 'required|max:180'),
                    'slug' => $this->field('Slug', 'text', 'required|max:180'),
                    'address_line_1' => $this->field('Address line 1', 'text', 'required|max:180'),
                    'address_line_2' => $this->field('Address line 2', 'text', 'nullable|max:180'),
                    'city' => $this->field('City', 'text', 'required|max:100'),
                    'state' => $this->field('State', 'text', 'required|max:40'),
                    'postal_code' => $this->field('Postal code', 'text', 'required|max:20'),
                    'capacity' => $this->field('Capacity', 'number', 'nullable|integer|min:0'),
                    'opened_year' => $this->field('Opened year', 'number', 'nullable|integer|min:1800|max:2100'),
                    'directions_url' => $this->field('Directions URL', 'url', 'nullable|max:2048'),
                    'amenities' => $this->field('Amenities', 'json', 'nullable|json', full: true),
                    'image_media_id' => $this->field('Venue image', 'select', 'nullable|exists:media_assets,id', 'media_images'),
                    'status' => $this->field('Publication status', 'select', 'required|in:draft,scheduled,published,archived', 'publication_statuses'),
                    'publish_at' => $this->field('Publish at', 'datetime-local', 'nullable|date'),
                ],
            ],
            'games' => [
                'label' => 'Games',
                'model' => Game::class,
                'permission' => 'manage schedule',
                'search' => ['slug'],
                'with' => ['season', 'homeTeam', 'awayTeam', 'venue'],
                'columns' => ['starts_at' => 'Date', 'awayTeam.name' => 'Away', 'homeTeam.name' => 'Home', 'workflow_status' => 'Publication'],
                'sort' => 'starts_at',
                'status_field' => 'publication_status',
                'workflow' => true,
                'fields' => [
                    'season_id' => $this->field('Season', 'select', 'required|exists:seasons,id', 'seasons'),
                    'away_team_id' => $this->field('Away team', 'select', 'required|exists:teams,id|different:form.home_team_id', 'teams'),
                    'home_team_id' => $this->field('Home team', 'select', 'required|exists:teams,id', 'teams'),
                    'venue_id' => $this->field('Venue', 'select', 'nullable|exists:venues,id', 'venues'),
                    'slug' => $this->field('Slug', 'text', 'required|max:200'),
                    'starts_at' => $this->field('Starts at', 'datetime-local', 'required|date'),
                    'timezone' => $this->field('Timezone', 'text', 'required|max:64|timezone'),
                    'status' => $this->field('Game status', 'select', 'required|in:scheduled,live,final,postponed,cancelled', 'game_statuses'),
                    'away_score' => $this->field('Away score', 'number', 'nullable|integer|min:0|max:999'),
                    'home_score' => $this->field('Home score', 'number', 'nullable|integer|min:0|max:999'),
                    'ticket_url' => $this->field('External ticket URL', 'url', 'nullable|max:2048'),
                    'broadcast_url' => $this->field('Broadcast URL', 'url', 'nullable|max:2048'),
                    'notes' => $this->field('Notes', 'textarea', 'nullable|max:5000', full: true),
                    'is_featured' => $this->field('Featured next game', 'checkbox', 'boolean'),
                    'publication_status' => $this->field('Publication status', 'select', 'required|in:draft,scheduled,published,archived', 'publication_statuses'),
                    'publish_at' => $this->field('Publish at', 'datetime-local', 'nullable|date'),
                ],
            ],
            'standings' => [
                'label' => 'Standings',
                'model' => Standing::class,
                'permission' => 'manage schedule',
                'search' => ['division'],
                'with' => ['season', 'team'],
                'columns' => ['rank' => 'Rank', 'team.name' => 'Team', 'wins' => 'Wins', 'losses' => 'Losses'],
                'sort' => 'rank',
                'fields' => [
                    'season_id' => $this->field('Season', 'select', 'required|exists:seasons,id', 'seasons'),
                    'team_id' => $this->field('Team', 'select', 'required|exists:teams,id', 'teams'),
                    'division' => $this->field('Division', 'text', 'nullable|max:120'),
                    'rank' => $this->field('Rank', 'number', 'required|integer|min:1'),
                    'wins' => $this->field('Wins', 'number', 'required|integer|min:0'),
                    'losses' => $this->field('Losses', 'number', 'required|integer|min:0'),
                    'win_percentage' => $this->field('Win percentage', 'number', 'required|numeric|min:0|max:1', attributes: [
                        'min' => '0',
                        'max' => '1',
                        'step' => '0.001',
                    ], help: 'Format: enter a ratio from 0.000 to 1.000. Example: 0.800 = 80%.'),
                    'position_order' => $this->field('Display order', 'number', 'required|integer|min:0'),
                ],
            ],
            'roster-memberships' => [
                'label' => 'Roster Memberships',
                'model' => RosterMembership::class,
                'permission' => 'manage roster',
                'search' => ['position', 'jersey_number'],
                'with' => ['season', 'person'],
                'columns' => ['person.display_name' => 'Player', 'season.name' => 'Season', 'jersey_number' => 'Number', 'position' => 'Position'],
                'sort' => 'position_order',
                'fields' => [
                    'season_id' => $this->field('Season', 'select', 'required|exists:seasons,id', 'seasons'),
                    'person_id' => $this->field('Player', 'select', 'required|exists:people,id', 'players'),
                    'jersey_number' => $this->field('Jersey number', 'text', 'nullable|max:8'),
                    'position' => $this->field('Position', 'text', 'required|max:40'),
                    'height' => $this->field('Height', 'text', 'nullable|max:24'),
                    'class_year' => $this->field('Class year', 'text', 'nullable|max:24'),
                    'position_order' => $this->field('Display order', 'number', 'required|integer|min:0'),
                    'is_featured' => $this->field('Featured', 'checkbox', 'boolean'),
                    'is_active' => $this->field('Active', 'checkbox', 'boolean'),
                ],
            ],
            'staff-assignments' => [
                'label' => 'Staff Assignments',
                'model' => StaffAssignment::class,
                'permission' => 'manage roster',
                'search' => ['role'],
                'with' => ['season', 'person'],
                'columns' => ['person.display_name' => 'Staff member', 'role' => 'Role', 'season.name' => 'Season', 'is_active' => 'Active'],
                'sort' => 'position_order',
                'fields' => [
                    'season_id' => $this->field('Season', 'select', 'nullable|exists:seasons,id', 'seasons'),
                    'person_id' => $this->field('Staff member', 'select', 'required|exists:people,id', 'staff'),
                    'role' => $this->field('Role', 'text', 'required|max:120'),
                    'position_order' => $this->field('Display order', 'number', 'required|integer|min:0'),
                    'is_leadership' => $this->field('Show on leadership section', 'checkbox', 'boolean'),
                    'is_active' => $this->field('Active', 'checkbox', 'boolean'),
                ],
            ],
            'posts' => [
                'label' => 'News Posts',
                'model' => Post::class,
                'permission' => 'manage news',
                'search' => ['title', 'slug', 'excerpt'],
                'with' => ['category', 'featuredMedia'],
                'columns' => ['title' => 'Title', 'category.name' => 'Category', 'workflow_status' => 'Status', 'published_at' => 'Published'],
                'sort' => 'published_at',
                'status_field' => 'status',
                'workflow' => true,
                'fields' => [
                    'category_id' => $this->field('Category', 'select', 'nullable|exists:categories,id', 'categories'),
                    'title' => $this->field('Title', 'text', 'required|max:220'),
                    'slug' => $this->field('Slug', 'text', 'required|max:220'),
                    'excerpt' => $this->field('Excerpt', 'textarea', 'nullable|max:1000', full: true),
                    'body' => $this->field('Body', 'textarea', 'nullable|max:50000', full: true),
                    'featured_media_id' => $this->field('Featured image', 'select', 'nullable|exists:media_assets,id', 'media_images'),
                    'is_featured' => $this->field('Featured', 'checkbox', 'boolean'),
                    'seo_title' => $this->field('SEO title', 'text', 'nullable|max:220'),
                    'seo_description' => $this->field('SEO description', 'textarea', 'nullable|max:500', full: true),
                    'status' => $this->field('Publication status', 'select', 'required|in:draft,scheduled,published,archived', 'publication_statuses'),
                    'publish_at' => $this->field('Publish at', 'datetime-local', 'nullable|date'),
                ],
            ],
            'categories' => [
                'label' => 'News Categories',
                'model' => Category::class,
                'permission' => 'manage news',
                'search' => ['name', 'slug'],
                'columns' => ['name' => 'Name', 'slug' => 'Slug'],
                'sort' => 'name',
                'fields' => [
                    'name' => $this->field('Name', 'text', 'required|max:120'),
                    'slug' => $this->field('Slug', 'text', 'required|max:160'),
                    'description' => $this->field('Description', 'textarea', 'nullable|max:2000', full: true),
                ],
            ],
            'sponsor-tiers' => [
                'label' => 'Sponsor Tiers',
                'model' => SponsorTier::class,
                'permission' => 'manage sponsors',
                'search' => ['name', 'slug'],
                'columns' => ['name' => 'Name', 'position' => 'Order', 'is_enabled' => 'Enabled'],
                'sort' => 'position',
                'fields' => [
                    'name' => $this->field('Name', 'text', 'required|max:160'),
                    'slug' => $this->field('Slug', 'text', 'required|max:180'),
                    'description' => $this->field('Description', 'textarea', 'nullable|max:5000', full: true),
                    'benefits' => $this->field('Benefits', 'json', 'nullable|json', full: true),
                    'position' => $this->field('Display order', 'number', 'required|integer|min:0'),
                    'is_enabled' => $this->field('Enabled', 'checkbox', 'boolean'),
                ],
            ],
            'sponsors' => [
                'label' => 'Sponsors',
                'model' => Sponsor::class,
                'permission' => 'manage sponsors',
                'search' => ['name', 'slug'],
                'with' => ['tier', 'logo'],
                'columns' => ['name' => 'Sponsor', 'tier.name' => 'Tier', 'position' => 'Order', 'workflow_status' => 'Status'],
                'sort' => 'position',
                'status_field' => 'status',
                'workflow' => true,
                'fields' => [
                    'sponsor_tier_id' => $this->field('Tier', 'select', 'nullable|exists:sponsor_tiers,id', 'sponsor_tiers'),
                    'name' => $this->field('Name', 'text', 'required|max:180'),
                    'slug' => $this->field('Slug', 'text', 'required|max:180'),
                    'description' => $this->field('Description', 'textarea', 'nullable|max:5000', full: true),
                    'website_url' => $this->field('Website URL', 'url', 'nullable|max:2048'),
                    'logo_media_id' => $this->field('Logo', 'select', 'nullable|exists:media_assets,id', 'media_images'),
                    'active_from' => $this->field('Active from', 'date', 'nullable|date'),
                    'active_until' => $this->field('Active until', 'date', 'nullable|date|after_or_equal:form.active_from'),
                    'position' => $this->field('Display order', 'number', 'required|integer|min:0'),
                    'is_featured' => $this->field('Featured', 'checkbox', 'boolean'),
                    'status' => $this->field('Publication status', 'select', 'required|in:draft,scheduled,published,archived', 'publication_statuses'),
                    'publish_at' => $this->field('Publish at', 'datetime-local', 'nullable|date'),
                ],
            ],
            'navigation' => [
                'label' => 'Navigation',
                'model' => NavigationItem::class,
                'permission' => 'manage settings',
                'search' => ['label', 'url', 'location'],
                'columns' => ['label' => 'Label', 'location' => 'Location', 'url' => 'URL', 'position' => 'Order'],
                'sort' => 'position',
                'fields' => [
                    'location' => $this->field('Location', 'select', 'required', 'navigation_locations'),
                    'label' => $this->field('Label', 'text', 'required|max:120'),
                    'url' => $this->field('URL', 'text', 'required|max:2048'),
                    'icon_media_id' => $this->field('Icon', 'select', 'nullable|exists:media_assets,id', 'media_icons'),
                    'target' => $this->field('Target', 'select', 'required', 'link_targets'),
                    'position' => $this->field('Display order', 'number', 'required|integer|min:0'),
                    'is_enabled' => $this->field('Enabled', 'checkbox', 'boolean'),
                ],
            ],
            'social-links' => [
                'label' => 'Social Links',
                'model' => SocialLink::class,
                'permission' => 'manage settings',
                'search' => ['platform', 'label', 'url'],
                'columns' => ['label' => 'Label', 'platform' => 'Platform', 'url' => 'URL', 'position' => 'Order'],
                'sort' => 'position',
                'fields' => [
                    'platform' => $this->field('Platform', 'text', 'required|max:40'),
                    'label' => $this->field('Label', 'text', 'required|max:120'),
                    'url' => $this->field('URL', 'url', 'required|max:2048'),
                    'icon_media_id' => $this->field('Icon', 'select', 'nullable|exists:media_assets,id', 'media_icons'),
                    'position' => $this->field('Display order', 'number', 'required|integer|min:0'),
                    'is_enabled' => $this->field('Enabled', 'checkbox', 'boolean'),
                ],
            ],
            'redirects' => [
                'label' => 'Redirects',
                'model' => Redirect::class,
                'permission' => 'manage settings',
                'search' => ['source_path', 'destination_url'],
                'columns' => ['source_path' => 'Source', 'destination_url' => 'Destination', 'status_code' => 'Code', 'hit_count' => 'Hits'],
                'sort' => 'source_path',
                'fields' => [
                    'source_path' => $this->field('Source path', 'text', 'required|max:2048'),
                    'destination_url' => $this->field('Destination URL', 'text', 'required|max:2048'),
                    'status_code' => $this->field('HTTP status', 'select', 'required', 'redirect_statuses'),
                    'is_enabled' => $this->field('Enabled', 'checkbox', 'boolean'),
                ],
            ],
        ];
    }

    public function get(string $key): array
    {
        abort_unless(array_key_exists($key, $this->all()), 404);

        return $this->all()[$key];
    }

    public function options(string $key): array
    {
        return match ($key) {
            'publication_statuses' => [
                'draft' => 'Draft',
                'scheduled' => 'Scheduled',
                'published' => 'Published',
                'archived' => 'Archived',
            ],
            'game_statuses' => [
                'scheduled' => 'Scheduled',
                'live' => 'Live',
                'final' => 'Final',
                'postponed' => 'Postponed',
                'cancelled' => 'Cancelled',
            ],
            'person_types' => ['player' => 'Player', 'staff' => 'Staff'],
            'navigation_locations' => ['primary' => 'Primary', 'footer' => 'Footer'],
            'link_targets' => ['_self' => 'Same window', '_blank' => 'New window'],
            'redirect_statuses' => [301 => '301 Permanent', 302 => '302 Temporary'],
            'seasons' => Season::query()->orderByDesc('starts_on')->pluck('name', 'id')->all(),
            'teams' => Team::query()->orderBy('name')->pluck('name', 'id')->all(),
            'venues' => Venue::query()->orderBy('name')->pluck('name', 'id')->all(),
            'categories' => Category::query()->orderBy('name')->pluck('name', 'id')->all(),
            'sponsor_tiers' => SponsorTier::query()->orderBy('position')->pluck('name', 'id')->all(),
            'players' => Person::query()->where('type', 'player')->orderBy('display_name')->pluck('display_name', 'id')->all(),
            'staff' => Person::query()->where('type', 'staff')->orderBy('display_name')->pluck('display_name', 'id')->all(),
            'media_images' => MediaAsset::query()->whereIn('kind', ['image', 'icon'])->orderBy('title')->pluck('title', 'id')->all(),
            'media_icons' => MediaAsset::query()->where('kind', 'icon')->orderBy('title')->pluck('title', 'id')->all(),
            default => [],
        };
    }

    private function field(
        string $label,
        string $type,
        string $rules,
        ?string $options = null,
        bool $full = false,
        array $attributes = [],
        ?string $help = null
    ): array {
        return compact('label', 'type', 'rules', 'options', 'full', 'attributes', 'help');
    }
}
