<?php

namespace App\Services\Newsletter;

use App\Contracts\NewsletterProvider;
use App\Models\NewsletterSubscriber;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MailchimpNewsletterProvider implements NewsletterProvider
{
    public function subscribe(NewsletterSubscriber $subscriber): ?string
    {
        $apiKey = (string) config('services.newsletter.mailchimp.api_key');
        $listId = (string) config('services.newsletter.mailchimp.list_id');
        $dataCenter = (string) config('services.newsletter.mailchimp.data_center');

        if (! $apiKey || ! $listId || ! $dataCenter) {
            throw new RuntimeException('Mailchimp newsletter credentials are incomplete.');
        }

        $memberId = md5(strtolower(trim($subscriber->email)));
        Http::withBasicAuth('dmv-warriors', $apiKey)
            ->acceptJson()
            ->put(
                "https://{$dataCenter}.api.mailchimp.com/3.0/lists/{$listId}/members/{$memberId}",
                [
                    'email_address' => $subscriber->email,
                    'status_if_new' => 'subscribed',
                    'status' => 'subscribed',
                ]
            )
            ->throw();

        return $memberId;
    }

    public function unsubscribe(NewsletterSubscriber $subscriber): void
    {
        $apiKey = (string) config('services.newsletter.mailchimp.api_key');
        $listId = (string) config('services.newsletter.mailchimp.list_id');
        $dataCenter = (string) config('services.newsletter.mailchimp.data_center');

        if (! $apiKey || ! $listId || ! $dataCenter) {
            throw new RuntimeException('Mailchimp newsletter credentials are incomplete.');
        }

        $memberId = $subscriber->provider_id
            ?: md5(strtolower(trim($subscriber->email)));
        Http::withBasicAuth('dmv-warriors', $apiKey)
            ->acceptJson()
            ->put(
                "https://{$dataCenter}.api.mailchimp.com/3.0/lists/{$listId}/members/{$memberId}",
                [
                    'email_address' => $subscriber->email,
                    'status_if_new' => 'unsubscribed',
                    'status' => 'unsubscribed',
                ]
            )
            ->throw();
    }
}
