<?php

namespace App\Services\Newsletter;

use App\Contracts\NewsletterProvider;
use App\Models\NewsletterSubscriber;
use Illuminate\Support\Facades\Log;

class LogNewsletterProvider implements NewsletterProvider
{
    public function subscribe(NewsletterSubscriber $subscriber): ?string
    {
        Log::info('Newsletter subscription accepted by log provider.', [
            'subscriber_uuid' => $subscriber->uuid,
        ]);

        return 'log-'.$subscriber->uuid;
    }

    public function unsubscribe(NewsletterSubscriber $subscriber): void
    {
        Log::info('Newsletter unsubscribe accepted by log provider.', [
            'subscriber_uuid' => $subscriber->uuid,
        ]);
    }
}
