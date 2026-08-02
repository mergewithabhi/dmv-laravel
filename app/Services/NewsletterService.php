<?php

namespace App\Services;

use App\Jobs\SyncNewsletterSubscriber;
use App\Models\NewsletterSubscriber;
use Illuminate\Support\Facades\Validator;

class NewsletterService
{
    public function subscribe(string $email, bool $consent = false): NewsletterSubscriber
    {
        $normalized = strtolower(trim($email));
        Validator::make(
            ['email' => $normalized, 'consent' => $consent],
            ['email' => ['required', 'email:rfc', 'max:254'], 'consent' => ['accepted']]
        )->validate();

        $hash = hash('sha256', $normalized);

        $subscriber = NewsletterSubscriber::query()->firstOrNew(['email_hash' => $hash]);
        $subscriber->fill([
            'email' => $normalized,
            'status' => 'pending',
            'provider' => config('services.newsletter.driver', 'log'),
            'consent' => $consent,
            'unsubscribed_at' => null,
        ])->save();

        SyncNewsletterSubscriber::dispatch($subscriber);

        return $subscriber;
    }
}
