<?php

namespace App\Livewire\Site\Concerns;

use App\Services\ScheduleDataService;
use App\Services\SiteChromeService;

trait BuildsSiteLayoutData
{
    protected function siteLayoutData(array $meta, array $structuredData): array
    {
        return app(SiteChromeService::class)->data() + [
            'meta' => $meta,
            'description' => $meta['description'] ?? null,
            'structuredData' => $structuredData,
            'calendarData' => app(ScheduleDataService::class)->calendarData(),
        ];
    }
}
