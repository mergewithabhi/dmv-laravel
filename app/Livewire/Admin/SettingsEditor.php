<?php

namespace App\Livewire\Admin;

use App\Models\MediaAsset;
use App\Models\SiteSetting;
use App\Rules\SafeUrl;
use App\Services\SiteChromeService;
use App\Services\AdminMediaUploadService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.admin')]
class SettingsEditor extends Component
{
    use WithFileUploads;

    public array $values = [];

    public array $mediaUploads = [];

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

    public function selectMedia(int $settingId, ?int $assetId): void
    {
        $this->authorizeAccess();
        $setting = SiteSetting::query()->where('type', 'media')->findOrFail($settingId);
        if ($assetId) {
            abort_unless(MediaAsset::query()->whereKey($assetId)->whereIn('kind', ['image', 'icon'])->exists(), 422);
        }
        $this->values[$setting->id] = $assetId;
    }

    public function uploadMedia(int $settingId, AdminMediaUploadService $uploader): void
    {
        $this->authorizeAccess();
        $setting = SiteSetting::query()->where('type', 'media')->findOrFail($settingId);
        $key = "setting-{$settingId}";
        $validator = Validator::make(
            ['upload' => $this->mediaUploads[$key] ?? null],
            ['upload' => ['required', 'file', 'max:'.config('cms.max_upload_kilobytes')]]
        );
        if ($validator->fails()) {
            $this->addError("mediaUploads.{$key}", $validator->errors()->first('upload'));

            return;
        }

        try {
            $asset = $uploader->store($this->mediaUploads[$key], 'image', $setting->label);
        } catch (ValidationException $exception) {
            $this->addError("mediaUploads.{$key}", $exception->errors()['upload'][0] ?? 'Upload failed.');

            return;
        }
        $this->values[$settingId] = $asset->id;
        unset($this->mediaUploads[$key]);
    }

    public function render()
    {
        $this->authorizeAccess();

        return view('livewire.admin.settings-editor', [
            'groups' => SiteSetting::query()->orderBy('group')->orderBy('label')->get()->groupBy('group'),
            'media' => MediaAsset::query()->with('media')->orderBy('title')->get(),
        ])->title('Settings')->layoutData(['heading' => 'Global Settings']);
    }

    private function authorizeAccess(): void
    {
        abort_unless(auth()->user()?->can('manage settings'), 403);
    }
}
