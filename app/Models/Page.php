<?php

namespace App\Models;

use App\Enums\PublicationStatus;
use App\Models\Concerns\HasContentRevisions;
use App\Models\Concerns\HasPublicationWorkflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Page extends Model
{
    use HasContentRevisions, HasPublicationWorkflow;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => PublicationStatus::class,
            'workflow_status' => PublicationStatus::class,
            'is_indexable' => 'boolean',
            'draft_snapshot' => 'array',
            'draft_saved_at' => 'datetime',
            'publish_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function sections(): HasMany
    {
        return $this->hasMany(PageSection::class)->orderBy('position');
    }

    public function ogMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'og_media_id');
    }
}
