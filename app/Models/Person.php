<?php

namespace App\Models;

use App\Enums\PublicationStatus;
use App\Models\Concerns\HasContentRevisions;
use App\Models\Concerns\HasPublicationWorkflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Person extends Model
{
    use HasContentRevisions, HasPublicationWorkflow, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'social_links' => 'array',
            'statistics' => 'array',
            'status' => PublicationStatus::class,
            'workflow_status' => PublicationStatus::class,
            'draft_snapshot' => 'array',
            'draft_saved_at' => 'datetime',
            'publish_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'photo_media_id');
    }

    public function rosterMemberships(): HasMany
    {
        return $this->hasMany(RosterMembership::class);
    }

    public function staffAssignments(): HasMany
    {
        return $this->hasMany(StaffAssignment::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
