@php
    $isMedia = in_array($field['options'], ['media_images', 'media_icons'], true);
    $isColor = str_ends_with($key, '_color');
@endphp
<div class="admin-field {{ $field['full'] || $isMedia || $field['type'] === 'json' ? 'full' : '' }}">
    @if ($field['type'] === 'checkbox')
        <label class="admin-switch">
            <input type="checkbox" wire:model="form.{{ $key }}">
            <span aria-hidden="true"></span>
            {{ $field['label'] }}
        </label>
    @else
        <label for="field-{{ $key }}">{{ $field['label'] }}</label>
        @if ($isMedia)
            @php
                $inputKind = $field['options'] === 'media_icons' ? 'icon' : 'image';
                $uploadKey = "resource-{$key}";
            @endphp
            @include('livewire.admin.partials.media-field', [
                'assets' => $media,
                'selectedId' => $form[$key] ?? null,
                'inputKind' => $inputKind,
                'inputId' => "field-{$key}",
                'uploadKey' => $uploadKey,
                'acceptedTypes' => $inputKind === 'icon' ? '.jpg,.jpeg,.png,.webp,.gif,.svg' : '.jpg,.jpeg,.png,.webp,.gif',
                'uploadAction' => "uploadMedia('{$key}')",
                'clearAction' => "selectMedia('{$key}', null)",
                'selectAction' => fn ($assetId) => "selectMedia('{$key}', {$assetId})",
            ])
        @elseif ($field['type'] === 'json')
            <div class="admin-repeater">
                @foreach ($repeaters[$key] ?? [] as $index => $row)
                    <div class="admin-repeater-row" wire:key="repeater-{{ $key }}-{{ $index }}">
                        @if (in_array($key, ['social_links', 'statistics'], true))
                            <input aria-label="{{ $field['label'] }} name" placeholder="{{ $key === 'social_links' ? 'Platform' : 'Label' }}" wire:model="repeaters.{{ $key }}.{{ $index }}.key">
                        @endif
                        <input aria-label="{{ $field['label'] }} value" placeholder="{{ in_array($key, ['social_links', 'statistics'], true) ? 'Value' : 'Add item' }}" wire:model="repeaters.{{ $key }}.{{ $index }}.value">
                        <button class="admin-icon-button" type="button" wire:click="removeRepeaterItem('{{ $key }}', {{ $index }})" title="Remove item" aria-label="Remove item">&times;</button>
                    </div>
                @endforeach
                <button class="admin-button secondary small" type="button" wire:click="addRepeaterItem('{{ $key }}')">Add item</button>
            </div>
        @elseif ($field['type'] === 'textarea')
            <textarea id="field-{{ $key }}" wire:model="form.{{ $key }}"></textarea>
        @elseif ($field['type'] === 'select')
            <select id="field-{{ $key }}" @if ($key === ($config['status_field'] ?? null)) wire:model.live="form.{{ $key }}" @else wire:model="form.{{ $key }}" @endif>
                <option value="">Select...</option>
                @foreach ($options[$field['options']] ?? [] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
            </select>
        @elseif ($isColor)
            <div class="admin-color-control">
                <input id="field-{{ $key }}" type="color" wire:model.live="form.{{ $key }}">
                <input aria-label="{{ $field['label'] }} value" wire:model.live="form.{{ $key }}">
            </div>
        @else
            <input id="field-{{ $key }}" type="{{ $field['type'] }}" wire:model="form.{{ $key }}">
        @endif
    @endif
    @error('form.'.$key)<span class="admin-field-error">{{ $message }}</span>@enderror
</div>
