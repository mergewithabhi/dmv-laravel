<x-layouts.auth title="Choose password">
    <header class="auth-header">
        <span class="auth-eyebrow">Account recovery</span>
        <h1>Choose a new password</h1>
        <p>Use a strong, unique password for your administration account.</p>
    </header>

    <form method="POST" action="{{ route('password.update') }}" class="auth-form">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

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
                    value="{{ old('email', $request->email) }}"
                    required
                    autocomplete="username"
                    @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                >
            </div>
            @error('email')<span class="auth-field-error" id="email-error">{{ $message }}</span>@enderror
        </div>

        <div class="auth-field">
            <label for="password">New password</label>
            <div class="auth-input-wrap">
                <svg aria-hidden="true" viewBox="0 0 24 24">
                    <rect x="5" y="10" width="14" height="11" rx="2"></rect>
                    <path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
                </svg>
                <input
                    id="password"
                    name="password"
                    type="password"
                    placeholder="Create a new password"
                    required
                    autocomplete="new-password"
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

        <div class="auth-field">
            <label for="password_confirmation">Confirm password</label>
            <div class="auth-input-wrap">
                <svg aria-hidden="true" viewBox="0 0 24 24">
                    <rect x="5" y="10" width="14" height="11" rx="2"></rect>
                    <path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
                </svg>
                <input
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    placeholder="Repeat your new password"
                    required
                    autocomplete="new-password"
                >
                <button
                    class="auth-password-toggle"
                    type="button"
                    data-password-toggle
                    aria-label="Show password"
                    aria-controls="password_confirmation"
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
        </div>

        <button class="auth-submit" type="submit">Reset password</button>
    </form>
</x-layouts.auth>
