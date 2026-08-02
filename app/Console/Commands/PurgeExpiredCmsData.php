<?php

namespace App\Console\Commands;

use App\Models\FormSubmission;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Spatie\Activitylog\Models\Activity;

#[Signature('cms:purge-expired {--dry-run : Report records without deleting them}')]
#[Description('Delete expired form submissions and audit events past their retention periods')]
class PurgeExpiredCmsData extends Command
{
    public function handle(): int
    {
        $submissionQuery = FormSubmission::query()
            ->whereNotNull('retention_until')
            ->where('retention_until', '<=', now());
        $activityQuery = Activity::query()
            ->where('created_at', '<', now()->subMonths((int) config('cms.retention.audit_months', 12)));

        $submissions = (clone $submissionQuery)->count();
        $activities = (clone $activityQuery)->count();

        if (! $this->option('dry-run')) {
            $submissionQuery->delete();
            $activityQuery->delete();
        }

        $action = $this->option('dry-run') ? 'Would delete' : 'Deleted';
        $this->info("{$action} {$submissions} submission(s) and {$activities} audit event(s).");

        return self::SUCCESS;
    }
}
