<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $title ?? 'CMS' }} | DMV Warriors</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="admin-body">
<a class="admin-skip-link" href="#admin-main-content">Skip to main content</a>
<div class="admin-shell">
    <aside class="admin-sidebar">
        <div class="admin-sidebar-header">
            <a class="admin-brand" href="{{ route('admin.dashboard') }}" wire:navigate>
                <img src="{{ asset('assets/bmv_logo_transparent.png') }}" alt="">
                <span><strong>DMV Warriors</strong>CMS Administration</span>
            </a>
            <button
                class="admin-nav-toggle"
                type="button"
                aria-expanded="false"
                aria-controls="admin-navigation"
                aria-label="Open CMS navigation"
            >
                <span aria-hidden="true"></span>
                <span aria-hidden="true"></span>
                <span aria-hidden="true"></span>
            </button>
        </div>
        <nav id="admin-navigation" class="admin-nav" aria-label="CMS navigation">
            <a href="{{ route('admin.dashboard') }}" wire:navigate class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"><img src="{{ asset('assets/icons/target.svg') }}" alt="">Dashboard</a>
            <div class="admin-nav-group">
                <span>Website content</span>
                @if (auth()->user()->can('manage pages') || auth()->user()->contentPermissions()->exists())<a href="{{ route('admin.pages') }}" wire:navigate class="{{ request()->routeIs('admin.pages*') ? 'active' : '' }}"><img src="{{ asset('assets/icons/clipboard.svg') }}" alt="">Website pages</a>@endif
                @can('manage news')<a href="{{ route('admin.resources', 'posts') }}" wire:navigate class="{{ request()->route('resource') === 'posts' ? 'active' : '' }}"><img src="{{ asset('assets/icons/mail.svg') }}" alt="">News</a>@endcan
                @can('manage news')<a href="{{ route('admin.resources', 'categories') }}" wire:navigate class="admin-nav-subtle {{ request()->route('resource') === 'categories' ? 'active' : '' }}"><img src="{{ asset('assets/icons/clipboard.svg') }}" alt="">News categories</a>@endcan
                @can('manage media')<a href="{{ route('admin.gallery') }}" wire:navigate class="{{ request()->routeIs('admin.gallery') ? 'active' : '' }}"><img src="{{ asset('assets/icons/binoculars.svg') }}" alt="">Website gallery</a>@endcan
                @can('manage media')<a href="{{ route('admin.media') }}" wire:navigate class="{{ request()->routeIs('admin.media') ? 'active' : '' }}"><img src="{{ asset('assets/icons/star.svg') }}" alt="">Images and files</a>@endcan
            </div>
            @can('manage roster')
                <div class="admin-nav-group"><span>Team</span>
                    <a href="{{ route('admin.resources', 'people') }}" wire:navigate class="{{ request()->route('resource') === 'people' ? 'active' : '' }}"><img src="{{ asset('assets/icons/users.svg') }}" alt="">Players and staff</a>
                    <a href="{{ route('admin.resources', 'roster-memberships') }}" wire:navigate class="{{ request()->route('resource') === 'roster-memberships' ? 'active' : '' }}"><img src="{{ asset('assets/icons/clipboard.svg') }}" alt="">Roster</a>
                    <a href="{{ route('admin.resources', 'staff-assignments') }}" wire:navigate class="{{ request()->route('resource') === 'staff-assignments' ? 'active' : '' }}"><img src="{{ asset('assets/icons/leader.svg') }}" alt="">Staff roles</a>
                </div>
            @endcan
            @can('manage schedule')
                <div class="admin-nav-group"><span>Schedule</span>
                    <a href="{{ route('admin.resources', 'games') }}" wire:navigate class="{{ request()->route('resource') === 'games' ? 'active' : '' }}"><img src="{{ asset('assets/icons/calendar.svg') }}" alt="">Games</a>
                    <a href="{{ route('admin.resources', 'standings') }}" wire:navigate class="{{ request()->route('resource') === 'standings' ? 'active' : '' }}"><img src="{{ asset('assets/icons/star.svg') }}" alt="">Standings</a>
                    <a href="{{ route('admin.resources', 'seasons') }}" wire:navigate class="{{ request()->route('resource') === 'seasons' ? 'active' : '' }}"><img src="{{ asset('assets/icons/clock.svg') }}" alt="">Seasons</a>
                    <a href="{{ route('admin.resources', 'teams') }}" wire:navigate class="{{ request()->route('resource') === 'teams' ? 'active' : '' }}"><img src="{{ asset('assets/icons/shield.svg') }}" alt="">Teams</a>
                    <a href="{{ route('admin.resources', 'venues') }}" wire:navigate class="{{ request()->route('resource') === 'venues' ? 'active' : '' }}"><img src="{{ asset('assets/icons/target.svg') }}" alt="">Venues</a>
                </div>
            @endcan
            @can('manage sponsors')
                <div class="admin-nav-group"><span>Partners</span>
                    <a href="{{ route('admin.resources', 'sponsors') }}" wire:navigate class="{{ request()->route('resource') === 'sponsors' ? 'active' : '' }}"><img src="{{ asset('assets/icons/handshake.svg') }}" alt="">Sponsors</a>
                    <a href="{{ route('admin.resources', 'sponsor-tiers') }}" wire:navigate class="{{ request()->route('resource') === 'sponsor-tiers' ? 'active' : '' }}"><img src="{{ asset('assets/icons/star.svg') }}" alt="">Sponsor levels</a>
                </div>
            @endcan
            @can('manage submissions')
                <div class="admin-nav-group"><span>Inbox</span>
                    <a href="{{ route('admin.submissions') }}" wire:navigate class="{{ request()->routeIs('admin.submissions*') ? 'active' : '' }}"><img src="{{ asset('assets/icons/mail.svg') }}" alt="">Messages</a>
                    <a href="{{ route('admin.newsletter-subscribers') }}" wire:navigate class="{{ request()->routeIs('admin.newsletter-subscribers') ? 'active' : '' }}"><img src="{{ asset('assets/icons/users.svg') }}" alt="">Newsletter</a>
                </div>
            @endcan
            <div class="admin-nav-group"><span>Administration</span>
                @can('manage settings')<a href="{{ route('admin.settings') }}" wire:navigate class="{{ request()->routeIs('admin.settings') ? 'active' : '' }}"><img src="{{ asset('assets/icons/shield.svg') }}" alt="">Website settings</a>@endcan
                @can('manage settings')<a href="{{ route('admin.resources', 'navigation') }}" wire:navigate class="{{ request()->route('resource') === 'navigation' ? 'active' : '' }}"><img src="{{ asset('assets/icons/arrow-right.svg') }}" alt="">Website menus</a>@endcan
                @can('manage settings')<a href="{{ route('admin.resources', 'social-links') }}" wire:navigate class="{{ request()->route('resource') === 'social-links' ? 'active' : '' }}"><img src="{{ asset('assets/icons/users.svg') }}" alt="">Social links</a>@endcan
                @can('manage settings')<a href="{{ route('admin.resources', 'redirects') }}" wire:navigate class="admin-nav-subtle {{ request()->route('resource') === 'redirects' ? 'active' : '' }}"><img src="{{ asset('assets/icons/arrow-right.svg') }}" alt="">Redirects</a>@endcan
                @can('manage users')<a href="{{ route('admin.users') }}" wire:navigate class="{{ request()->routeIs('admin.users') ? 'active' : '' }}"><img src="{{ asset('assets/icons/leader.svg') }}" alt="">CMS users</a>@endcan
                @can('view audit log')<a href="{{ route('admin.audit') }}" wire:navigate class="admin-nav-subtle {{ request()->routeIs('admin.audit') ? 'active' : '' }}"><img src="{{ asset('assets/icons/clock.svg') }}" alt="">Activity log</a>@endcan
                <a href="{{ route('admin.security') }}" wire:navigate class="{{ request()->routeIs('admin.security') ? 'active' : '' }}"><img src="{{ asset('assets/icons/shield.svg') }}" alt="">My account</a>
            </div>
            <a href="{{ route('home') }}" target="_blank" rel="noopener"><img src="{{ asset('assets/icons/arrow-right.svg') }}" alt="">View live website</a>
        </nav>
    </aside>
    <div class="admin-main">
        <header class="admin-topbar">
            <h1>{{ $heading ?? 'Content Management System' }}</h1>
            <div class="admin-user">
                <span>{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="admin-button secondary small" type="submit">Sign out</button>
                </form>
            </div>
        </header>
        <main id="admin-main-content" class="admin-content" tabindex="-1">
            @if (session('success'))<div class="admin-alert" role="status">{{ session('success') }}</div>@endif
            @if (session('error'))<div class="admin-alert error" role="alert">{{ session('error') }}</div>@endif
            {{ $slot }}
        </main>
    </div>
</div>
@include('components.confirm-dialog')
@livewireScripts
</body>
</html>
