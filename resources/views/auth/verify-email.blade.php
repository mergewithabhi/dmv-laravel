<x-layouts.auth title="Verify email">
    <header class="auth-header">
        <span class="auth-eyebrow">Email verification</span>
        <h1>Check your inbox</h1>
        <p>Open the verification link sent to your email address before entering the dashboard.</p>
    </header>

    @if (session('status') === 'verification-link-sent')
        <div class="auth-alert" role="status">
            <img src="{{ asset('assets/icons/shield.svg') }}" alt="">
            A new verification link has been sent.
        </div>
    @endif

    <form method="POST" action="{{ route('verification.send') }}" class="auth-form">
        @csrf
        <button class="auth-submit" type="submit">Resend verification email</button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="auth-form">
        @csrf
        <button class="auth-back-link" type="submit">Sign out</button>
    </form>
</x-layouts.auth>
