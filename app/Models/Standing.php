<?php

namespace App\Models;

use App\Models\Concerns\HasContentRevisions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Standing extends Model
{
    use HasContentRevisions, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['win_percentage' => 'decimal:3'];
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
