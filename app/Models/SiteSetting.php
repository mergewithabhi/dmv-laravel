<?php

namespace App\Models;

use App\Models\Concerns\HasContentRevisions;
use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    use HasContentRevisions;

    protected $guarded = ['id'];

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
