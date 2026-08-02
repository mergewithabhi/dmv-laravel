<?php

namespace App\Models;

use App\Enums\PublicationStatus;
use App\Models\Concerns\HasContentRevisions;
use App\Models\Concerns\HasPublicationWorkflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team extends Model
{
    use HasContentRevisions, HasPublicationWorkflow, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_home_team' => 'boolean',
            'status' => PublicationStatus::class,
            'workflow_status' => PublicationStatus::class,
            'draft_snapshot' => 'array',
            'draft_saved_at' => 'datetime',
            'publish_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function logo(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'logo_media_id');
    }
}
