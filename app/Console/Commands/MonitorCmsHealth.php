<?php

namespace App\Console\Commands;

use App\Models\SiteSetting;
use App\Notifications\FailedJobsDetected;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Throwable;

#[Signature('cms:monitor')]
#[Description('Send an alert when new failed queue jobs are detected')]
class MonitorCmsHealth extends Command
{
    public function handle(): int
    {
        if (! Schema::hasTable('failed_jobs')) {
            return self::SUCCESS;
        }

        $lastNotifiedId = (int) Cache::get('cms.monitor.last_failed_job_id', 0);
        $newFailures = DB::table('failed_jobs')->where('id', '>', $lastNotifiedId);
        $count = (clone $newFailures)->count();
        $latestId = (int) ((clone $newFailures)->max('id') ?? 0);

        if ($count === 0) {
            return self::SUCCESS;
        }

        $recipient = config('cms.alert_email')
            ?: SiteSetting::value('contact.notification_email')
            ?: config('mail.from.address');

        if (! $recipient) {
            Log::critical('Failed queue jobs detected but CMS_ALERT_EMAIL is not configured.', [
                'new_failed_jobs' => $count,
            ]);

            return self::FAILURE;
        }

        try {
            Notification::route('mail', $recipient)
                ->notifyNow(new FailedJobsDetected($count));
            Cache::forever('cms.monitor.last_failed_job_id', $latestId);
            $this->info("Alerted {$recipient} about {$count} failed job(s).");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Log::critical('Could not send the failed-job alert.', [
                'recipient' => $recipient,
                'new_failed_jobs' => $count,
                'exception' => $exception->getMessage(),
            ]);

            return self::FAILURE;
        }
    }
}
