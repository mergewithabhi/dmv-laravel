<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FailedJobsDetected extends Notification
{
    public function __construct(public int $count) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('DMV Warriors CMS failed job alert')
            ->greeting('Queue processing needs attention')
            ->line("{$this->count} new queued job(s) failed.")
            ->action('Open the CMS dashboard', route('admin.dashboard'))
            ->line('Review the failed job records and application logs before retrying them.');
    }
}
