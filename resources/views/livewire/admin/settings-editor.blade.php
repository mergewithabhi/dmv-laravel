<div>
    <div class="admin-page-heading">
        <div><h2>Global settings</h2><p>Header, footer, branding, contact details, ticket links, and public system copy.</p></div>
        <div class="admin-actions">
            <span class="guided-save-state" wire:dirty wire:target="values">Unsaved changes</span>
            <button class="admin-button" type="submit" form="global-settings-form" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">Save changes</span>
                <span wire:loading wire:target="save">Saving...</span>
            </button>
        </div>
    </div>
    <section class="admin-panel instagram-connection-panel">
        <div class="admin-panel-header">
            <h3>Instagram feed</h3>
            @if ($instagramConnection)
                <span class="status-badge published">Connected</span>
            @else
                <span class="status-badge archived">Not connected</span>
            @endif
        </div>
        <div class="admin-panel-body instagram-connection-body">
            <div class="instagram-connection-summary">
                <div class="instagram-connection-account">
                    <img src="{{ asset('assets/icons/instagram.svg') }}" alt="">
                    <div>
                        @if ($instagramConnection)
                            <strong>{{ $instagramConnection->username ? '@'.$instagramConnection->username : 'Instagram account' }}</strong>
                            <small>
                                @if ($instagramConnection->expires_at)
                                    Token refresh due by {{ $instagramConnection->expires_at->format('M j, Y') }}
                                @else
                                    Connected
                                @endif
                            </small>
                        @else
                            <strong>Connect an Instagram account</strong>
                        @endif
                    </div>
                </div>
                @if ($instagramConnection)
                    <div class="admin-actions">
                        <form method="POST" action="{{ route('admin.instagram.destroy') }}">
                            @csrf
                            @method('DELETE')
                            <button
                                class="admin-button danger"
                                type="submit"
                                data-confirm-title="Disconnect Instagram?"
                                data-confirm-message="The automatic Home page feed will return to its fallback images."
                                data-confirm-button="Disconnect"
                                data-confirm-variant="danger"
                            >Disconnect</button>
                        </form>
                    </div>
                @endif
            </div>
            <form class="instagram-token-form" method="POST" action="{{ route('admin.instagram.store') }}">
                @csrf
                <div class="admin-field">
                    <label for="instagram-access-token">{{ $instagramConnection ? 'Replace access token' : 'Instagram access token' }}</label>
                    <input
                        id="instagram-access-token"
                        name="access_token"
                        type="password"
                        required
                        maxlength="4096"
                        autocomplete="new-password"
                        spellcheck="false"
                    >
                    <small class="admin-field-help">The token is encrypted after validation and is never shown again.</small>
                </div>
                <button class="admin-button" type="submit">{{ $instagramConnection ? 'Replace token' : 'Connect account' }}</button>
            </form>
        </div>
    </section>
    <form id="global-settings-form" wire:submit="save">
        @foreach ($groups as $group => $settings)
            <section class="admin-panel section-editor">
                <div class="admin-panel-header"><h3>{{ \Illuminate\Support\Str::headline($group) }}</h3></div>
                <div class="admin-panel-body admin-form-grid">
                    @foreach ($settings as $setting)
                        <div class="admin-field {{ $setting->type === 'textarea' ? 'full' : '' }}">
                            <label for="setting-{{ $setting->id }}">{{ $setting->label }}</label>
                            @if ($setting->type === 'textarea')
                                <textarea id="setting-{{ $setting->id }}" wire:model="values.{{ $setting->id }}"></textarea>
                            @elseif ($setting->type === 'boolean')
                                <label class="admin-switch">
                                    <input id="setting-{{ $setting->id }}" type="checkbox" wire:model="values.{{ $setting->id }}">
                                    <span aria-hidden="true"></span>
                                    Enabled
                                </label>
                            @elseif ($setting->type === 'media')
                                @include('livewire.admin.partials.media-field', [
                                    'assets' => $media,
                                    'selectedId' => $values[$setting->id] ?? null,
                                    'inputKind' => 'image',
                                    'inputId' => "setting-{$setting->id}",
                                    'uploadKey' => "setting-{$setting->id}",
                                    'acceptedTypes' => '.jpg,.jpeg,.png,.webp,.gif',
                                    'uploadAction' => "uploadMedia({$setting->id})",
                                    'clearAction' => "selectMedia({$setting->id}, null)",
                                    'selectAction' => fn ($assetId) => "selectMedia({$setting->id}, {$assetId})",
                                ])
                            @else
                                <input
                                    id="setting-{{ $setting->id }}"
                                    type="{{ in_array($setting->type, ['email','number']) ? $setting->type : 'text' }}"
                                    @if($setting->type === 'url') inputmode="url" placeholder="/page or https://example.com" @endif
                                    wire:model="values.{{ $setting->id }}"
                                >
                            @endif
                            @if ($setting->key === 'footer.link_text')
                                <small class="admin-field-help">Optional. Leave blank to make all Footer values text clickable.</small>
                            @endif
                            @error('values.'.$setting->id)<span class="admin-field-error">{{ $message }}</span>@enderror
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
        <button class="admin-button" type="submit" wire:loading.attr="disabled" wire:target="save">Save changes</button>
    </form>
</div>
