<?php

return [
    'alert_email' => env('CMS_ALERT_EMAIL'),

    'security' => [
        'require_two_factor' => (bool) env('CMS_REQUIRE_TWO_FACTOR', true),
    ],

    'static_import_asset_sources' => [
        base_path('legacy-static/assets'),
        public_path('assets'),
    ],

    'templates' => [
        'home' => [
            'title' => 'Home',
            'file' => 'home.html',
            'sections' => [
                'hero' => ['label' => 'Hero', 'selector' => '.hero'],
                'statistics' => ['label' => 'Statistics', 'selector' => '.stats-strip'],
                'introduction' => ['label' => 'Introduction and next game', 'selector' => '.intro-grid'],
                'news' => ['label' => 'Latest news', 'selector' => '.news-section'],
                'team_schedule' => ['label' => 'Team and schedule', 'selector' => '.team-schedule-grid'],
                'partners' => ['label' => 'Partners', 'selector' => '.partners-section'],
                'community' => ['label' => 'Community and social gallery', 'selector' => '.community-grid'],
                'newsletter' => ['label' => 'Newsletter', 'selector' => '.newsletter'],
                'sponsor_cta' => ['label' => 'Sponsor call to action', 'selector' => '.sponsor-cta'],
            ],
        ],
        'about' => [
            'title' => 'About Us',
            'file' => 'about.html',
            'sections' => [
                'hero' => ['label' => 'Hero', 'selector' => '.about-hero'],
                'story' => ['label' => 'Story', 'selector' => '.about-story'],
                'statistics' => ['label' => 'Statistics', 'selector' => '.about-stat-bar'],
                'mission_vision' => ['label' => 'Mission and vision', 'selector' => '.mission-vision-grid'],
                'values' => ['label' => 'Core values', 'selector' => '.about-values-section'],
                'leadership' => ['label' => 'Leadership', 'selector' => '.leadership-section'],
                'community' => ['label' => 'Community impact', 'selector' => '.community-impact'],
                'timeline' => ['label' => 'Journey timeline', 'selector' => '.journey-section'],
                'why' => ['label' => 'Why DMV Warriors', 'selector' => '.about-why'],
                'family' => ['label' => 'Family gallery and call to action', 'selector' => '.about-family'],
            ],
        ],
        'roster' => [
            'title' => 'Roster',
            'file' => 'roster.html',
            'sections' => [
                'hero' => ['label' => 'Hero', 'selector' => '.roster-hero'],
                'statistics' => ['label' => 'Statistics', 'selector' => '.roster-stat-bar'],
                'introduction' => ['label' => 'Team introduction', 'selector' => '.roster-intro'],
                'players' => ['label' => 'Players', 'selector' => '.full-roster-section'],
                'coaches' => ['label' => 'Coaching staff', 'selector' => '.coaching-section'],
                'cta' => ['label' => 'Closing call to action', 'selector' => '.roster-cta'],
            ],
        ],
        'schedule' => [
            'title' => 'Schedule',
            'file' => 'schedule.html',
            'sections' => [
                'hero' => ['label' => 'Hero', 'selector' => '.schedule-hero'],
                'statistics' => ['label' => 'Statistics', 'selector' => '.schedule-stat-bar'],
                'next_game' => ['label' => 'Next game and calendar', 'selector' => '.next-calendar-grid'],
                'season' => ['label' => 'Schedule and standings', 'selector' => '.season-layout'],
                'venue' => ['label' => 'Venue and game day', 'selector' => '.venue-game-grid'],
                'season_stats' => ['label' => 'Season statistics', 'selector' => '.season-stats'],
                'partners' => ['label' => 'Partners', 'selector' => '.schedule-partners'],
                'sponsor_cta' => ['label' => 'Sponsor call to action', 'selector' => '.schedule-sponsor-cta'],
            ],
        ],
        'sponsors' => [
            'title' => 'Sponsors',
            'file' => 'sponsors.html',
            'sections' => [
                'hero' => ['label' => 'Hero', 'selector' => '.sponsor-hero'],
                'statistics' => ['label' => 'Statistics', 'selector' => '.sponsor-stat-bar'],
                'partners' => ['label' => 'Sponsor tiers and benefits', 'selector' => '.sponsor-partners'],
                'form' => ['label' => 'Sponsor inquiry form', 'selector' => '.sponsor-form-panel'],
                'cta' => ['label' => 'Partnership call to action', 'selector' => '.sponsor-final-cta'],
            ],
        ],
        'contact' => [
            'title' => 'Contact',
            'file' => 'contact.html',
            'sections' => [
                'contact' => ['label' => 'Hero, contact details, and form', 'selector' => '.contact-stage'],
                'social' => ['label' => 'Stay connected', 'selector' => '.stay-connected'],
            ],
        ],
    ],

    'legacy_redirects' => [
        '/index.html' => '/',
        '/about.html' => '/about',
        '/roster.html' => '/roster',
        '/schedule.html' => '/schedule',
        '/sponsors.html' => '/sponsors',
        '/contact.html' => '/contact',
    ],

    'allowed_upload_mimes' => [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/svg+xml',
        'video/mp4',
        'video/webm',
        'application/pdf',
        'text/calendar',
    ],

    'max_upload_kilobytes' => 10240,

    'retention' => [
        'submission_months' => (int) env('CMS_SUBMISSION_RETENTION_MONTHS', 24),
        'audit_months' => (int) env('CMS_AUDIT_RETENTION_MONTHS', 12),
    ],

    'backup' => [
        'disk' => env('CMS_BACKUP_DISK', 'local'),
        'path' => env('CMS_BACKUP_PATH', 'backups'),
        'retention_days' => (int) env('CMS_BACKUP_RETENTION_DAYS', 30),
        'mysqldump_binary' => env('MYSQLDUMP_BINARY', 'mysqldump'),
    ],
];
