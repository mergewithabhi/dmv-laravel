<x-layouts.auth title="Reset password">
    <header class="auth-header">
        <span class="auth-eyebrow">Account recovery</span>
        <h1>Reset your password</h1>
        <p>Enter your account email and we will send you a secure reset link.</p>
    </header>

    @if (session('status'))
        <div class="auth-alert" role="status">
            <img src="{{ asset('assets/icons/shield.svg') }}" alt="">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="auth-form">
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

        <button class="auth-submit" type="submit">
            Send reset link
            <svg aria-hidden="true" viewBox="0 0 24 24">
                <path d="M5 12h14M13 6l6 6-6 6"></path>
            </svg>
        </button>
    </form>

    <a class="auth-back-link" href="{{ route('login') }}">&larr; Back to sign in</a>
</x-layouts.auth>
