<?php

namespace App\Models;

use App\Models\Concerns\HasContentRevisions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageSection extends Model
{
    use HasContentRevisions;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'field_schema' => 'array',
            'payload' => 'array',
            'is_enabled' => 'boolean',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
