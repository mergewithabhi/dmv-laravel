<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NewsletterSubscriber extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function (self $subscriber): void {
            $subscriber->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'email' => 'encrypted',
            'consent' => 'boolean',
            'subscribed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }
}
