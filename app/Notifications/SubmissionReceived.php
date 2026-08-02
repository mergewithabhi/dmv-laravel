<?php

namespace App\Notifications;

use App\Models\FormSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubmissionReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public FormSubmission $submission) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New DMV Warriors '.ucfirst($this->submission->type).' submission')
            ->greeting('New website submission')
            ->line('A new '.$this->submission->type.' submission has been saved.')
            ->line('From: '.($this->submission->name ?: 'Not provided'))
            ->line('Email: '.($this->submission->email ?: 'Not provided'))
            ->action('Review submission', url('/admin/submissions/'.$this->submission->id));
    }

    public function toArray(object $notifiable): array
    {
        return ['submission_id' => $this->submission->id];
    }
}
