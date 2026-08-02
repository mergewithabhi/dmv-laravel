<x-layouts.auth title="Sign in">
    <header class="auth-header">
        <span class="auth-eyebrow">Administration portal</span>
        <h1>Welcome back</h1>
        <p>Sign in with your approved team account.</p>
    </header>

    @if (session('status'))
        <div class="auth-alert" role="status">
            <img src="{{ asset('assets/icons/shield.svg') }}" alt="">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="auth-form">
        @csrf
        <div class="auth-field">
            <label for="email">Email address</label>
            <div class="auth-input-wrap">
                <svg aria-hidden="true" viewBox="0 0 24 24">
                    <rect x="3" y="5" width="18" height="14" rx="2"></rect>
                    <path d="m4.5 7 7.5 6 7.5-6"></path>
                </svg>
                <input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    placeholder="name@dmvwarriors.com"
                    required
                    autofocus
                    autocomplete="username"
                    @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                >
            </div>
            @error('email')<span class="auth-field-error" id="email-error">{{ $message }}</span>@enderror
        </div>

        <div class="auth-field">
            <label for="password">Password</label>
            <div class="auth-input-wrap">
                <svg aria-hidden="true" viewBox="0 0 24 24">
                    <rect x="5" y="10" width="14" height="11" rx="2"></rect>
                    <path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
                </svg>
                <input
                    id="password"
                    name="password"
                    type="password"
                    placeholder="Enter your password"
                    required
                    autocomplete="current-password"
                    @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                >
                <button
                    class="auth-password-toggle"
                    type="button"
                    data-password-toggle
                    aria-label="Show password"
                    aria-controls="password"
                    title="Show password"
                >
                    <svg data-eye-open aria-hidden="true" viewBox="0 0 24 24">
                        <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"></path>
                        <circle cx="12" cy="12" r="2.5"></circle>
                    </svg>
                    <svg data-eye-closed aria-hidden="true" viewBox="0 0 24 24" hidden>
                        <path d="m3 3 18 18M10.7 6.1A9.7 9.7 0 0 1 12 6c6 0 9.5 6 9.5 6a16 16 0 0 1-2.2 2.8M6.1 6.1C3.8 7.8 2.5 12 2.5 12s3.5 6 9.5 6c1.5 0 2.9-.4 4.1-1"></path>
                    </svg>
                </button>
            </div>
            @error('password')<span class="auth-field-error" id="password-error">{{ $message }}</span>@enderror
        </div>

        <div class="auth-options">
            <label class="auth-checkbox" for="remember">
                <input id="remember" name="remember" type="checkbox" value="1">
                <span>Keep me signed in</span>
            </label>
            <a href="{{ route('password.request') }}">Forgot password?</a>
        </div>

        <button class="auth-submit" type="submit">
            Sign in to dashboard
            <svg aria-hidden="true" viewBox="0 0 24 24">
                <path d="M5 12h14M13 6l6 6-6 6"></path>
            </svg>
        </button>
    </form>

    <p class="auth-note">
        Having trouble accessing your account?
        <a href="{{ route('site.page', ['slug' => 'contact']) }}">Contact the site administrator</a>.
    </p>
</x-layouts.auth>
