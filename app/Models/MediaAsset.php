<?php

namespace App\Models;

use App\Enums\MediaKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaAsset extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function (self $asset): void {
            $asset->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'kind' => MediaKind::class,
            'focal_x' => 'decimal:2',
            'focal_y' => 'decimal:2',
            'is_decorative' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function galleryItems(): HasMany
    {
        return $this->hasMany(GalleryItem::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('file')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        if (! $media || ! str_starts_with((string) $media->mime_type, 'image/')) {
            return;
        }

        if ($media->mime_type === 'image/svg+xml') {
            return;
        }

        $this->addMediaConversion('thumb')
            ->width(480)
            ->height(320)
            ->format('webp')
            ->nonQueued();

        $this->addMediaConversion('web')
            ->width(1600)
            ->format('webp')
            ->nonQueued();
    }

    public function url(string $conversion = ''): ?string
    {
        $media = $this->getFirstMedia('file');

        if (! $media) {
            return null;
        }

        return $conversion && $media->hasGeneratedConversion($conversion)
            ? $media->getUrl($conversion)
            : $media->getUrl();
    }
}
