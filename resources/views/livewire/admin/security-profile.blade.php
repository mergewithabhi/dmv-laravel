<div>
    <div class="admin-page-heading">
        <div>
            <h2>Account security</h2>
            <p>Protect your CMS account with an authenticator app and recovery codes.</p>
        </div>
        <span class="status-badge {{ $enabled ? 'published' : 'in_review' }}">
            {{ $enabled ? '2FA enabled' : ($required ? '2FA required' : '2FA optional') }}
        </span>
    </div>

    @if (! $enabled)
        <section
            id="two-factor-setup"
            class="admin-panel"
            tabindex="-1"
            aria-labelledby="two-factor-setup-heading"
            data-admin-action-area
        >
            <div class="admin-panel-header"><h3 id="two-factor-setup-heading">Authenticator setup</h3></div>
            <div class="admin-panel-body security-setup">
                @if (! $qrCode)
                    <p>Generate a QR code, scan it with your authenticator app, then enter the current six-digit code.</p>
                    <button class="admin-button" type="button" wire:click="enable" data-admin-focus-target="#two-factor-setup">Generate setup code</button>
                @else
                    <div class="security-qr" aria-label="Two-factor authentication QR code">{!! $qrCode !!}</div>
                    <form class="admin-form-grid" wire:submit="confirm">
                        <div class="admin-field full">
                            <label for="two-factor-code">Authentication code</label>
                            <input id="two-factor-code" type="text" inputmode="numeric" autocomplete="one-time-code" wire:model="code">
                            @error('code')<span class="admin-field-error">{{ $message }}</span>@enderror
                            @error('confirmTwoFactorAuthentication.code')<span class="admin-field-error">{{ $message }}</span>@enderror
                        </div>
                        <button class="admin-button" type="submit">Confirm 2FA</button>
                    </form>
                @endif
            </div>
        </section>
    @else
        <section
            id="two-factor-recovery"
            class="admin-panel"
            tabindex="-1"
            aria-labelledby="two-factor-recovery-heading"
            data-admin-action-area
        >
            <div class="admin-panel-header"><h3 id="two-factor-recovery-heading">Recovery access</h3></div>
            <div class="admin-panel-body">
                <p>Recovery codes are single-use credentials. Store them in a password manager.</p>
                @if ($recoveryCodes)
                    <ul class="security-recovery-codes">
                        @foreach ($recoveryCodes as $recoveryCode)
                            <li><code>{{ $recoveryCode }}</code></li>
                        @endforeach
                    </ul>
                @endif
                <div class="admin-actions">
                    <button
                        class="admin-button secondary"
                        type="button"
                        wire:click="regenerate"
                        data-admin-focus-target="#two-factor-recovery"
                        data-confirm-title="Replace recovery codes?"
                        data-confirm-message="All existing recovery codes will stop working immediately."
                        data-confirm-button="Replace codes"
                        data-confirm-variant="warning"
                    >Generate new recovery codes</button>
                    @unless ($required)
                        <button
                            class="admin-button danger"
                            type="button"
                            wire:click="disable"
                            data-confirm-title="Disable two-factor authentication?"
                            data-confirm-message="This account will no longer require an authenticator code during sign in."
                            data-confirm-button="Disable 2FA"
                        >Disable 2FA</button>
                    @endunless
                </div>
            </div>
        </section>
    @endif
</div>
