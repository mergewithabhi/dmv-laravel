<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FormSubmission extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function (self $submission): void {
            $submission->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
            'name' => 'encrypted',
            'email' => 'encrypted',
            'phone' => 'encrypted',
            'subject' => 'encrypted',
            'payload' => 'encrypted:array',
            'consent' => 'boolean',
            'retention_until' => 'datetime',
            'exported_at' => 'datetime',
        ];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
