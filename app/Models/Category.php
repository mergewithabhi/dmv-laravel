<?php

namespace App\Models;

use App\Models\Concerns\HasContentRevisions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasContentRevisions, SoftDeletes;

    protected $guarded = ['id'];

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
