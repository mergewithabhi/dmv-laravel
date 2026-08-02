<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'CMS Sign In' }} | DMV Warriors</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-body auth-body">
    <main class="auth-shell">
        <aside class="auth-showcase" aria-label="DMV Warriors administration">
            <img
                class="auth-showcase-media"
                src="{{ asset('assets/main_hero_banner.png') }}"
                alt=""
                aria-hidden="true"
            >
            <div class="auth-showcase-overlay" aria-hidden="true"></div>

            <div class="auth-showcase-top">
                <a class="auth-brand" href="/" aria-label="DMV Warriors home">
                    <img src="{{ asset('assets/bmv_logo_transparent.png') }}" alt="">
                    <span>
                        <strong>DMV Warriors</strong>
                        <small>Administration</small>
                    </span>
                </a>
                <!-- <span class="auth-secure-badge">
                    <img src="{{ asset('assets/icons/shield.svg') }}" alt="">
                    Secure access
                </span> -->
            </div>

            <div class="auth-showcase-copy">
                <p class="auth-kicker">DMV Warriors CMS</p>
                <p class="auth-showcase-title">Run the season.<br>Manage the story.</p>
                <p>One secure workspace for team content, schedules, rosters, partners, and fan communications.</p>
                <span class="auth-authorized"><i aria-hidden="true"></i> Authorized team personnel only</span>
            </div>
        </aside>

        <section class="auth-content">
            <div class="auth-content-inner">
                <a class="auth-mobile-brand" href="/" aria-label="DMV Warriors home">
                    <img src="{{ asset('assets/bmv_logo_transparent.png') }}" alt="">
                    <span><strong>DMV Warriors</strong><small>Administration</small></span>
                </a>

                <div class="auth-panel">
                    {{ $slot }}
                </div>

                <footer class="auth-help">
                    <span>Protected administrative access</span>
                    <a href="{{ route('site.page', ['slug' => 'contact']) }}">Contact support</a>
                </footer>
            </div>
        </section>
    </main>
    @include('components.confirm-dialog')
</body>
</html>
