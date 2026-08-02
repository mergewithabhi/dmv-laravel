<div>
    <div class="admin-page-heading">
        <div>
            <h2>Content permissions for {{ $targetUser->name }}</h2>
            <p>Grant access to specific pages, sections, and field groups without changing this user's role.</p>
        </div>
        <div class="admin-actions">
            <a class="admin-button secondary" href="{{ route('admin.users') }}" wire:navigate>Back to users</a>
        </div>
    </div>

    <form wire:submit="save">
        @foreach ($grants as $templateKey => $page)
            <details class="admin-panel section-editor" @if($loop->first) open @endif>
                <summary class="admin-panel-header">
                    <h3>{{ ucfirst($templateKey) }}</h3>
                    <label><input type="checkbox" wire:model="grants.{{ $templateKey }}.all"> All sections and fields</label>
                </summary>
                <div class="admin-panel-body" @if($page['all']) style="opacity:.5" @endif>
                    @foreach ($page['sections'] as $sectionKey => $section)
                        <div class="admin-field full">
                            <label>
                                <input type="checkbox" wire:model="grants.{{ $templateKey }}.sections.{{ $sectionKey }}.all" @disabled($page['all'])>
                                <strong>{{ \Illuminate\Support\Str::headline($sectionKey) }}</strong> &mdash; all field groups
                            </label>
                            <div class="admin-form-grid">
                                @foreach ($fieldGroups as $group)
                                    <label>
                                        <input
                                            type="checkbox"
                                            wire:model="grants.{{ $templateKey }}.sections.{{ $sectionKey }}.groups.{{ $group->value }}"
                                            @disabled($page['all'] || $section['all'])
                                        >
                                        {{ $group->label() }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </details>
        @endforeach

        <div class="admin-actions">
            <button class="admin-button" type="submit" wire:loading.attr="disabled">Save permissions</button>
        </div>
    </form>
</div>
