<?php

namespace App\Models;

use App\Models\Concerns\HasContentRevisions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Redirect extends Model
{
    use HasContentRevisions, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'last_hit_at' => 'datetime',
        ];
    }
}
