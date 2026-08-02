<?php

namespace App\Models;

use App\Models\Concerns\HasContentRevisions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SponsorTier extends Model
{
    use HasContentRevisions, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'benefits' => 'array',
            'is_enabled' => 'boolean',
        ];
    }

    public function sponsors(): HasMany
    {
        return $this->hasMany(Sponsor::class)->orderBy('position');
    }
}
