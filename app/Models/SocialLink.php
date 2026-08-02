<?php

namespace App\Models;

use App\Models\Concerns\HasContentRevisions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SocialLink extends Model
{
    use HasContentRevisions, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }

    public function icon(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'icon_media_id');
    }
}
