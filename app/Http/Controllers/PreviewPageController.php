<?php

namespace App\Http\Controllers;

use App\Domain\Content\FixedTemplateRenderer;
use App\Models\Page;
use App\Services\PageWorkflowService;
use App\Services\ScheduleDataService;
use App\Services\SiteChromeService;

class PreviewPageController extends Controller
{
    public function __invoke(
        Page $page,
        FixedTemplateRenderer $renderer,
        SiteChromeService $chrome,
        ScheduleDataService $schedule,
        PageWorkflowService $workflow
    ) {
        $page = $workflow->applyDraftForPreview($page->load(['sections', 'ogMedia']));

        return view('site.preview', [
            'page' => $page,
            'content' => $renderer->render($page),
            'calendarData' => $schedule->calendarData(),
            'description' => $page->seo_description,
            'structuredData' => [
                '@context' => 'https://schema.org',
                '@type' => 'SportsTeam',
                'name' => 'DMV Warriors',
                'sport' => 'Basketball',
            ],
        ] + $chrome->data());
    }
}
