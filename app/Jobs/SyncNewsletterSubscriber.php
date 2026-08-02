<?php

namespace App\Jobs;

use App\Contracts\NewsletterProvider;
use App\Models\NewsletterSubscriber;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncNewsletterSubscriber implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(public NewsletterSubscriber $subscriber) {}

    public function handle(NewsletterProvider $provider): void
    {
        $this->subscriber->refresh();
        if ($this->subscriber->status === 'unsubscribed') {
            return;
        }

        try {
            $providerId = $provider->subscribe($this->subscriber);
            $this->subscriber->forceFill([
                'status' => 'subscribed',
                'provider_id' => $providerId,
                'subscribed_at' => $this->subscriber->subscribed_at ?: now(),
                'last_synced_at' => now(),
                'last_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            $this->subscriber->forceFill([
                'last_synced_at' => now(),
                'last_error' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }
    }
}
