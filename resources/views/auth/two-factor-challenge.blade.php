<x-layouts.auth title="Two-factor challenge">
    <header class="auth-header">
        <span class="auth-eyebrow">Identity verification</span>
        <h1>Two-factor authentication</h1>
        <p>Enter your authenticator code. Use a recovery code only when your authenticator is unavailable.</p>
    </header>

    <form method="POST" action="{{ route('two-factor.login') }}" class="auth-form">
        @csrf
        <div class="auth-field">
            <label for="code">Authentication code</label>
            <div class="auth-input-wrap auth-input-wrap-plain">
                <input
                    id="code"
                    name="code"
                    inputmode="numeric"
                    placeholder="000 000"
                    autocomplete="one-time-code"
                    autofocus
                    @error('code') aria-invalid="true" aria-describedby="code-error" @enderror
                >
            </div>
            @error('code')<span class="auth-field-error" id="code-error">{{ $message }}</span>@enderror
        </div>

        <div class="auth-field">
            <label for="recovery_code">Recovery code</label>
            <div class="auth-input-wrap auth-input-wrap-plain">
                <input
                    id="recovery_code"
                    name="recovery_code"
                    placeholder="Enter a recovery code"
                    autocomplete="one-time-code"
                    @error('recovery_code') aria-invalid="true" aria-describedby="recovery-code-error" @enderror
                >
            </div>
            @error('recovery_code')<span class="auth-field-error" id="recovery-code-error">{{ $message }}</span>@enderror
        </div>

        <button class="auth-submit" type="submit">Verify and continue</button>
    </form>

    <form method="POST" action="{{ route('two-factor.cancel') }}" class="auth-form">
        @csrf
        <button class="auth-back-link" type="submit">Cancel and return to sign in</button>
    </form>
</x-layouts.auth>
