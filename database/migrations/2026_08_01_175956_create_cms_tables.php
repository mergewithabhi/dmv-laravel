<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('kind', 24)->default('image')->index();
            $table->string('title');
            $table->text('alt_text')->nullable();
            $table->text('caption')->nullable();
            $table->string('credit')->nullable();
            $table->decimal('focal_x', 5, 2)->default(50);
            $table->decimal('focal_y', 5, 2)->default(50);
            $table->boolean('is_decorative')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('template_key', 40)->unique();
            $table->string('title');
            $table->string('status', 24)->default('draft')->index();
            $table->boolean('is_indexable')->default(true);
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->string('canonical_url')->nullable();
            $table->foreignId('og_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamp('publish_at')->nullable()->index();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('page_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->string('section_key', 80);
            $table->string('label');
            $table->unsignedSmallInteger('position');
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->boolean('is_enabled')->default(true);
            $table->json('field_schema');
            $table->json('payload');
            $table->longText('template_html')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->unique(['page_id', 'section_key']);
            $table->index(['page_id', 'position']);
        });

        Schema::create('content_revisions', function (Blueprint $table) {
            $table->id();
            $table->string('revisionable_type');
            $table->unsignedBigInteger('revisionable_id');
            $table->unsignedInteger('version');
            $table->string('event', 32);
            $table->json('snapshot');
            $table->text('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(
                ['revisionable_type', 'revisionable_id'],
                'content_revisions_revisionable_index'
            );
            $table->unique(
                ['revisionable_type', 'revisionable_id', 'version'],
                'content_revisions_version_unique'
            );
        });

        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 60)->index();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('type', 32)->default('text');
            $table->json('value')->nullable();
            $table->boolean('is_public')->default(true);
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
        });

        Schema::create('navigation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('navigation_items')->cascadeOnDelete();
            $table->string('location', 40)->default('primary')->index();
            $table->string('label');
            $table->string('url');
            $table->foreignId('icon_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('target', 16)->default('_self');
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('social_links', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 40);
            $table->string('label');
            $table->string('url');
            $table->foreignId('icon_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('source_path')->unique();
            $table->string('destination_url');
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('is_enabled')->default(true);
            $table->unsignedBigInteger('hit_count')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();
        });

        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_current')->default(false)->index();
            $table->string('status', 24)->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->string('slug')->unique();
            $table->string('abbreviation', 8)->nullable();
            $table->string('primary_color', 16)->nullable();
            $table->string('secondary_color', 16)->nullable();
            $table->foreignId('logo_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->boolean('is_home_team')->default(false);
            $table->string('status', 24)->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('city');
            $table->string('state', 40);
            $table->string('postal_code', 20);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedSmallInteger('opened_year')->nullable();
            $table->json('amenities')->nullable();
            $table->string('directions_url')->nullable();
            $table->foreignId('image_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('status', 24)->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->string('type', 24)->index();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('slug')->unique();
            $table->string('display_name');
            $table->string('hometown')->nullable();
            $table->text('biography')->nullable();
            $table->json('social_links')->nullable();
            $table->json('statistics')->nullable();
            $table->foreignId('photo_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('status', 24)->default('draft')->index();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamp('publish_at')->nullable()->index();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('roster_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->string('jersey_number', 8)->nullable();
            $table->string('position', 40);
            $table->string('height', 24)->nullable();
            $table->string('class_year', 24)->nullable();
            $table->unsignedSmallInteger('position_order')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['season_id', 'person_id']);
        });

        Schema::create('staff_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->unsignedSmallInteger('position_order')->default(0);
            $table->boolean('is_leadership')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['season_id', 'person_id', 'role']);
        });

        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->foreignId('home_team_id')->constrained('teams')->restrictOnDelete();
            $table->foreignId('away_team_id')->constrained('teams')->restrictOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained()->nullOnDelete();
            $table->string('slug')->unique();
            $table->dateTimeTz('starts_at')->index();
            $table->string('timezone', 64)->default('America/New_York');
            $table->string('status', 24)->default('scheduled')->index();
            $table->unsignedSmallInteger('home_score')->nullable();
            $table->unsignedSmallInteger('away_score')->nullable();
            $table->string('ticket_url')->nullable();
            $table->string('broadcast_url')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_featured')->default(false)->index();
            $table->string('publication_status', 24)->default('draft')->index();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamp('publish_at')->nullable()->index();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('standings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('division')->nullable();
            $table->unsignedSmallInteger('rank');
            $table->unsignedSmallInteger('wins')->default(0);
            $table->unsignedSmallInteger('losses')->default(0);
            $table->decimal('win_percentage', 5, 3)->default(0);
            $table->unsignedSmallInteger('position_order')->default(0);
            $table->timestamps();
            $table->unique(['season_id', 'team_id', 'division']);
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->foreignId('featured_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('status', 24)->default('draft')->index();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamp('publish_at')->nullable()->index();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('sponsor_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->json('benefits')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('sponsors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sponsor_tier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('website_url')->nullable();
            $table->foreignId('logo_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->date('active_from')->nullable();
            $table->date('active_until')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->string('status', 24)->default('draft')->index();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamp('publish_at')->nullable()->index();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type', 32)->index();
            $table->string('status', 24)->default('new')->index();
            $table->text('name')->nullable();
            $table->text('email')->nullable();
            $table->string('email_hash', 64)->nullable()->index();
            $table->text('phone')->nullable();
            $table->text('subject')->nullable();
            $table->longText('payload');
            $table->string('ip_hash', 64)->nullable();
            $table->boolean('consent')->default(false);
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('internal_notes')->nullable();
            $table->timestamp('retention_until')->nullable()->index();
            $table->timestamp('exported_at')->nullable();
            $table->timestamps();
        });

        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->text('email');
            $table->string('email_hash', 64)->unique();
            $table->string('status', 24)->default('pending')->index();
            $table->string('provider', 32)->default('log');
            $table->string('provider_id')->nullable();
            $table->boolean('consent')->default(false);
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
        Schema::dropIfExists('form_submissions');
        Schema::dropIfExists('sponsors');
        Schema::dropIfExists('sponsor_tiers');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('standings');
        Schema::dropIfExists('games');
        Schema::dropIfExists('staff_assignments');
        Schema::dropIfExists('roster_memberships');
        Schema::dropIfExists('people');
        Schema::dropIfExists('venues');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('seasons');
        Schema::dropIfExists('redirects');
        Schema::dropIfExists('social_links');
        Schema::dropIfExists('navigation_items');
        Schema::dropIfExists('site_settings');
        Schema::dropIfExists('content_revisions');
        Schema::dropIfExists('page_sections');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('media_assets');
    }
};
