<div>
    <div class="admin-page-heading"><div><h2>Global settings</h2><p>Header, footer, branding, contact details, ticket links, and public system copy.</p></div></div>
    <form wire:submit="save">
        @foreach ($groups as $group => $settings)
            <section class="admin-panel section-editor">
                <div class="admin-panel-header"><h3>{{ \Illuminate\Support\Str::headline($group) }}</h3></div>
                <div class="admin-panel-body admin-form-grid">
                    @foreach ($settings as $setting)
                        <div class="admin-field {{ $setting->type === 'textarea' ? 'full' : '' }}">
                            <label for="setting-{{ $setting->id }}">{{ $setting->label }}</label>
                            @if ($setting->type === 'textarea')
                                <textarea id="setting-{{ $setting->id }}" wire:model="values.{{ $setting->id }}"></textarea>
                            @elseif ($setting->type === 'media')
                                <select id="setting-{{ $setting->id }}" wire:model="values.{{ $setting->id }}"><option value="">No media</option>@foreach($media as $asset)<option value="{{ $asset->id }}">{{ $asset->title }}</option>@endforeach</select>
                            @else
                                <input id="setting-{{ $setting->id }}" type="{{ in_array($setting->type, ['email','url','number']) ? $setting->type : 'text' }}" wire:model="values.{{ $setting->id }}">
                            @endif
                            @error('values.'.$setting->id)<span class="admin-field-error">{{ $message }}</span>@enderror
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
        <button class="admin-button" type="submit">Save global settings</button>
    </form>
</div>
