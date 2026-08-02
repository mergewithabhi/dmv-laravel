<?php

namespace App\Models;

use App\Enums\PublicationStatus;
use App\Models\Concerns\HasContentRevisions;
use App\Models\Concerns\HasPublicationWorkflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Venue extends Model
{
    use HasContentRevisions, HasPublicationWorkflow, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amenities' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'status' => PublicationStatus::class,
            'workflow_status' => PublicationStatus::class,
            'draft_snapshot' => 'array',
            'draft_saved_at' => 'datetime',
            'publish_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'image_media_id');
    }

    public function formattedAddress(): string
    {
        return collect([
            $this->address_line_1,
            $this->address_line_2,
            "{$this->city}, {$this->state} {$this->postal_code}",
        ])->filter()->implode(', ');
    }
}
