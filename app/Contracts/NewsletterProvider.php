<?php

namespace App\Contracts;

use App\Models\NewsletterSubscriber;

interface NewsletterProvider
{
    public function subscribe(NewsletterSubscriber $subscriber): ?string;

    public function unsubscribe(NewsletterSubscriber $subscriber): void;
}
