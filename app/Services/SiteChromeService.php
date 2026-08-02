<?php

namespace App\Services;

use App\Models\MediaAsset;
use App\Models\NavigationItem;
use App\Models\SiteSetting;
use App\Models\SocialLink;
use Illuminate\Support\Facades\Cache;

class SiteChromeService
{
    public function data(): array
    {
        $chrome = Cache::rememberForever('site.chrome.scalar', function (): array {
            return [
                'settings' => SiteSetting::query()
                    ->where('is_public', true)
                    ->get()
                    ->mapWithKeys(fn (SiteSetting $setting) => [
                        $setting->key => $setting->value['value'] ?? null,
                    ])
                    ->all(),
                'navigation_ids' => NavigationItem::query()
                    ->where('is_enabled', true)
                    ->orderBy('position')
                    ->pluck('id')
                    ->all(),
                'social_ids' => SocialLink::query()
                    ->where('is_enabled', true)
                    ->orderBy('position')
                    ->pluck('id')
                    ->all(),
            ];
        });
        $settings = $chrome['settings'];

        $mediaIds = collect($settings)
            ->filter(fn ($value, $key) => str_ends_with($key, '_media_id') && is_numeric($value))
            ->map(fn ($value) => (int) $value)
            ->all();
        $navigationOrder = array_flip($chrome['navigation_ids']);
        $socialOrder = array_flip($chrome['social_ids']);
        $navigation = NavigationItem::query()
            ->with(['icon.media'])
            ->whereKey($chrome['navigation_ids'])
            ->get()
            ->sortBy(fn (NavigationItem $item) => $navigationOrder[$item->id] ?? PHP_INT_MAX)
            ->values();
        $socialLinks = SocialLink::query()
            ->with(['icon.media'])
            ->whereKey($chrome['social_ids'])
            ->get()
            ->sortBy(fn (SocialLink $link) => $socialOrder[$link->id] ?? PHP_INT_MAX)
            ->values();

        return [
            'settings' => $settings,
            'settingMedia' => MediaAsset::query()->with('media')->whereIn('id', $mediaIds)->get()->keyBy('id'),
            'navigation' => $navigation
                ->where('location', 'primary')
                ->whereNull('parent_id')
                ->values(),
            'footerNavigation' => $navigation->where('location', 'footer')->values(),
            'socialLinks' => $socialLinks,
        ];
    }

    public function forget(): void
    {
        Cache::forget('site.chrome.scalar');
    }
}
