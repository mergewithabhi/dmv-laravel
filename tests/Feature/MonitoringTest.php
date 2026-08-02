<?php

namespace Tests\Feature;

use App\Notifications\FailedJobsDetected;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_monitor_alerts_once_for_each_new_failed_job_batch(): void
    {
        Notification::fake();
        config()->set('cms.alert_email', 'ops@example.com');
        DB::table('failed_jobs')->insert([
            'uuid' => 'failed-job-test',
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'Test failure',
            'failed_at' => now(),
        ]);

        $this->artisan('cms:monitor')->assertSuccessful();
        $this->artisan('cms:monitor')->assertSuccessful();

        Notification::assertSentOnDemandTimes(FailedJobsDetected::class, 1);
    }
}
