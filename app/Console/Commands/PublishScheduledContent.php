<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\Page;
use App\Models\Person;
use App\Models\Post;
use App\Models\Season;
use App\Models\Sponsor;
use App\Models\Team;
use App\Models\Venue;
use App\Services\PageWorkflowService;
use App\Services\SiteChromeService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cms:publish-scheduled')]
#[Description('Publish approved CMS records whose scheduled time has arrived')]
class PublishScheduledContent extends Command
{
    public function handle(SiteChromeService $chrome, PageWorkflowService $pageWorkflow): int
    {
        $published = 0;

        Page::query()
            ->where('workflow_status', 'scheduled')
            ->whereNotNull('publish_at')
            ->where('publish_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($records) use (&$published, $pageWorkflow): void {
                foreach ($records as $record) {
                    if ($pageWorkflow->publishScheduled($record)) {
                        $published++;
                        activity('publishing')
                            ->performedOn($record)
                            ->withProperties(['source' => 'scheduler'])
                            ->log('published scheduled page draft');
                    }
                }
            });

        foreach ([
            Person::class,
            Season::class,
            Team::class,
            Venue::class,
            Post::class,
            Game::class,
            Sponsor::class,
        ] as $modelClass) {
            $modelClass::query()
                ->where('workflow_status', 'scheduled')
                ->whereNotNull('publish_at')
                ->where('publish_at', '<=', now())
                ->orderBy('id')
                ->chunkById(100, function ($records) use (&$published): void {
                    foreach ($records as $record) {
                        if ($record->publishScheduled()) {
                            $published++;
                            activity('publishing')
                                ->performedOn($record)
                                ->withProperties(['source' => 'scheduler'])
                                ->log('published scheduled content');
                        }
                    }
                });
        }

        if ($published > 0) {
            $chrome->forget();
        }

        $this->info("Published {$published} scheduled record(s).");

        return self::SUCCESS;
    }
}
