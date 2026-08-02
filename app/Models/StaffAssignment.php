<?php

namespace App\Models;

use App\Models\Concerns\HasContentRevisions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StaffAssignment extends Model
{
    use HasContentRevisions, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_leadership' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
