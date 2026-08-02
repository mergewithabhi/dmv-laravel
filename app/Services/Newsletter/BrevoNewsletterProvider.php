<?php

namespace App\Services\Newsletter;

use App\Contracts\NewsletterProvider;
use App\Models\NewsletterSubscriber;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BrevoNewsletterProvider implements NewsletterProvider
{
    public function subscribe(NewsletterSubscriber $subscriber): ?string
    {
        $apiKey = (string) config('services.newsletter.brevo.api_key');
        $listId = config('services.newsletter.brevo.list_id');

        if (! $apiKey || ! $listId) {
            throw new RuntimeException('Brevo newsletter credentials are incomplete.');
        }

        Http::withHeaders(['api-key' => $apiKey])
            ->acceptJson()
            ->post('https://api.brevo.com/v3/contacts', [
                'email' => $subscriber->email,
                'listIds' => [(int) $listId],
                'updateEnabled' => true,
            ])
            ->throw();

        return strtolower(trim($subscriber->email));
    }

    public function unsubscribe(NewsletterSubscriber $subscriber): void
    {
        $apiKey = (string) config('services.newsletter.brevo.api_key');
        if (! $apiKey) {
            throw new RuntimeException('Brevo newsletter credentials are incomplete.');
        }

        Http::withHeaders(['api-key' => $apiKey])
            ->acceptJson()
            ->delete(
                'https://api.brevo.com/v3/contacts/'.rawurlencode($subscriber->email)
            )
            ->throw();
    }
}
