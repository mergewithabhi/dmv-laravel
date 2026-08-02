<x-layouts.auth title="Confirm password">
    <header class="auth-header">
        <span class="auth-eyebrow">Security check</span>
        <h1>Confirm your password</h1>
        <p>This area contains sensitive settings. Re-enter your password to continue.</p>
    </header>

    <form method="POST" action="{{ route('password.confirm') }}" class="auth-form">
        @csrf
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

        <button class="auth-submit" type="submit">Confirm and continue</button>
    </form>
</x-layouts.auth>
