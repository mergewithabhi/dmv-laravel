<?php

namespace App\Models;

use App\Enums\PublicationStatus;
use App\Models\Concerns\HasContentRevisions;
use App\Models\Concerns\HasPublicationWorkflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Season extends Model
{
    use HasContentRevisions, HasPublicationWorkflow, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_current' => 'boolean',
            'status' => PublicationStatus::class,
            'workflow_status' => PublicationStatus::class,
            'draft_snapshot' => 'array',
            'draft_saved_at' => 'datetime',
            'publish_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    public function rosterMemberships(): HasMany
    {
        return $this->hasMany(RosterMembership::class);
    }

    public function standings(): HasMany
    {
        return $this->hasMany(Standing::class)->orderBy('rank');
    }

    public function staffAssignments(): HasMany
    {
        return $this->hasMany(StaffAssignment::class);
    }
}
