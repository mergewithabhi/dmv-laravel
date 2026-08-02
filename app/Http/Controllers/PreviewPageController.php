<?php

namespace App\Http\Controllers;

use App\Domain\Content\FixedTemplateRenderer;
use App\Models\Page;
use App\Services\PageWorkflowService;
use App\Services\ScheduleDataService;
use App\Services\SiteChromeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PreviewPageController extends Controller
{
    public function __invoke(
        Request $request,
        Page $page,
        FixedTemplateRenderer $renderer,
        SiteChromeService $chrome,
        ScheduleDataService $schedule,
        PageWorkflowService $workflow
    ) {
        $page = $page->load(['sections', 'ogMedia']);
        $token = (string) $request->query('editor');
        $snapshot = $token !== ''
            ? Cache::get("cms-page-preview:".auth()->id().":{$page->id}:{$token}")
            : null;
        $page = is_array($snapshot)
            ? $workflow->applySnapshotForPreview($page, $snapshot)
            : $workflow->applyDraftForPreview($page);

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
