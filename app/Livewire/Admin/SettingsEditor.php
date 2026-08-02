<?php

namespace App\Livewire\Admin;

use App\Models\MediaAsset;
use App\Models\SiteSetting;
use App\Rules\SafeUrl;
use App\Services\SiteChromeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.admin')]
class SettingsEditor extends Component
{
    public array $values = [];

    #[Locked]
    public array $versions = [];

    public function mount(): void
    {
        $this->authorizeAccess();

        foreach (SiteSetting::query()->get() as $setting) {
            $this->values[$setting->id] = $setting->value['value'] ?? null;
            $this->versions[$setting->id] = (int) $setting->lock_version;
        }
    }

    public function save(SiteChromeService $chrome): void
    {
        $this->authorizeAccess();
        $settings = SiteSetting::query()->get();
        $rules = [];
        foreach ($settings as $setting) {
            $rules['values.'.$setting->id] = match ($setting->type) {
                'email' => ['nullable', 'email', 'max:254'],
                'url' => ['nullable', 'string', 'max:2048', new SafeUrl],
                'number' => ['nullable', 'numeric'],
                'media' => ['nullable', 'integer', 'exists:media_assets,id'],
                default => ['nullable', 'string', 'max:10000'],
            };
        }
        $validated = $this->validate($rules)['values'];

        DB::transaction(function () use ($validated): void {
            $lockedSettings = SiteSetting::query()->lockForUpdate()->get();

            foreach ($lockedSettings as $setting) {
                if (($this->versions[$setting->id] ?? null) !== (int) $setting->lock_version) {
                    throw ValidationException::withMessages([
                        'values.'.$setting->id => 'This setting changed in another session. Reload before saving.',
                    ]);
                }

                $value = $validated[$setting->id] ?? null;
                if (in_array($setting->type, ['number', 'media'], true) && $value !== null && $value !== '') {
                    $value = (int) $value;
                }

                $setting->update([
                    'value' => ['value' => $value],
                    'lock_version' => $setting->lock_version + 1,
                ]);
                $this->versions[$setting->id] = (int) $setting->lock_version;
            }
        });

        $chrome->forget();
        activity('cms')->causedBy(auth()->user())->log('updated global settings');
        session()->flash('success', 'Global settings were saved.');
    }

    public function render()
    {
        $this->authorizeAccess();

        return view('livewire.admin.settings-editor', [
            'groups' => SiteSetting::query()->orderBy('group')->orderBy('label')->get()->groupBy('group'),
            'media' => MediaAsset::query()->orderBy('title')->get(),
        ])->title('Settings')->layoutData(['heading' => 'Global Settings']);
    }

    private function authorizeAccess(): void
    {
        abort_unless(auth()->user()?->can('manage settings'), 403);
    }
}
