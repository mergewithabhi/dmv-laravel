<?php

namespace App\Services;

use App\Models\FormSubmission;
use App\Models\SiteSetting;
use App\Notifications\SubmissionReceived;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SubmissionService
{
    public function create(string $type, array $data, Request $request): FormSubmission
    {
        if (! in_array($type, ['contact', 'sponsor'], true)) {
            throw ValidationException::withMessages([
                'form' => 'This form type is not supported.',
            ]);
        }

        Validator::make(
            ['consent' => $data['consent'] ?? false],
            ['consent' => ['accepted']]
        )->validate();

        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $submission = FormSubmission::query()->create([
            'type' => $type,
            'name' => $data['name'] ?? null,
            'email' => $email ?: null,
            'email_hash' => $email ? hash('sha256', $email) : null,
            'phone' => $data['phone'] ?? null,
            'subject' => $data['subject'] ?? $data['level'] ?? null,
            'payload' => $data,
            'ip_hash' => $request->ip()
                ? hash_hmac('sha256', $request->ip(), (string) config('app.key'))
                : null,
            'consent' => true,
            'retention_until' => now()->addMonths((int) config('cms.retention.submission_months', 24)),
        ]);

        $recipient = trim((string) SiteSetting::value(
            'contact.notification_email',
            config('mail.from.address')
        ));

        if ($recipient !== '' && filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            try {
                Notification::route('mail', $recipient)
                    ->notify(new SubmissionReceived($submission));
            } catch (\Throwable $exception) {
                Log::error('Submission was saved but its notification could not be queued.', [
                    'submission_uuid' => $submission->uuid,
                    'exception' => $exception->getMessage(),
                ]);
            }
        } elseif ($recipient !== '') {
            Log::error('Submission notification email is invalid.', [
                'submission_uuid' => $submission->uuid,
            ]);
        }

        return $submission;
    }
}
