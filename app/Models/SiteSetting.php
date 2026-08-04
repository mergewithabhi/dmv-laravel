<?php

namespace App\Models;

use App\Models\Concerns\HasContentRevisions;
use App\Services\SiteChromeService;
use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    use HasContentRevisions;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::saved(fn () => app(SiteChromeService::class)->forget());
        static::deleted(fn () => app(SiteChromeService::class)->forget());
    }

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'is_public' => 'boolean',
        ];
    }

    public static function value(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('key', $key)->first();

        return $setting?->value['value'] ?? $default;
    }

    public static function setValue(
        string $key,
        mixed $value,
        string $label,
        string $group = 'general',
        string $type = 'text'
    ): self {
        return static::query()->updateOrCreate(
            ['key' => $key],
            compact('label', 'group', 'type') + ['value' => ['value' => $value]]
        );
    }
}
