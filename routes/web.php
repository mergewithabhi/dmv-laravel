<?php

use App\Http\Controllers\CalendarController;
use App\Http\Controllers\InstagramConnectionController;
use App\Http\Controllers\PreviewPageController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SponsorPackController;
use App\Livewire\Admin\AuditLog;
use App\Livewire\Admin\ContentPermissionsEditor;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\GalleryManager;
use App\Livewire\Admin\MediaLibrary;
use App\Livewire\Admin\NewsletterSubscribers;
use App\Livewire\Admin\PageEditor;
use App\Livewire\Admin\PagesIndex;
use App\Livewire\Admin\ResourceManager;
use App\Livewire\Admin\SecurityProfile;
use App\Livewire\Admin\SettingsEditor;
use App\Livewire\Admin\SubmissionsInbox;
use App\Livewire\Admin\UsersManager;
use App\Livewire\Site\GameShow;
use App\Livewire\Site\GalleryIndex;
use App\Livewire\Site\NewsIndex;
use App\Livewire\Site\NewsShow;
use App\Livewire\Site\PlayerShow;
use App\Livewire\Site\SitePage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/robots.txt', fn () => response(
    "User-agent: *\nAllow: /\nSitemap: ".route('sitemap')."\n",
    200,
    ['Content-Type' => 'text/plain; charset=utf-8']
))->name('robots');

Route::get('/schedule/calendar.ics', CalendarController::class)
    ->middleware('throttle:60,1')
    ->name('schedule.calendar');
Route::get('/schedule/calendar/{season}.ics', CalendarController::class)
    ->where('season', '[A-Za-z0-9-]+')
    ->middleware('throttle:60,1')
    ->name('schedule.calendar.season');
Route::get('/sponsor-pack', SponsorPackController::class)
    ->middleware('throttle:30,1')
    ->name('sponsor-pack');

Route::post('/two-factor-challenge/cancel', function (Request $request) {
    $request->session()->forget(['login.id', 'login.remember']);
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('guest')->name('two-factor.cancel');

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/security', SecurityProfile::class)
        ->middleware('password.confirm')
        ->name('security');

    Route::middleware('2fa.confirmed')->group(function (): void {
        Route::get('/', Dashboard::class)->name('dashboard');
        Route::get('/pages', PagesIndex::class)->name('pages');
        Route::get('/pages/{page}/edit', PageEditor::class)->name('pages.edit');
        Route::get('/pages/{page}/preview', PreviewPageController::class)
            ->middleware('signed')
            ->name('pages.preview');
        Route::get('/media', MediaLibrary::class)
            ->middleware('permission:manage media')
            ->name('media');
        Route::get('/gallery', GalleryManager::class)
            ->middleware('permission:manage media')
            ->name('gallery');
        Route::get('/content/{resource}', ResourceManager::class)->name('resources');
        Route::get('/submissions', SubmissionsInbox::class)
            ->middleware('permission:manage submissions')
            ->name('submissions');
        Route::get('/submissions/{submission}', SubmissionsInbox::class)
            ->middleware('permission:manage submissions')
            ->name('submissions.show');
        Route::get('/newsletter-subscribers', NewsletterSubscribers::class)
            ->middleware('permission:manage submissions')
            ->name('newsletter-subscribers');
        Route::get('/settings', SettingsEditor::class)
            ->middleware('permission:manage settings')
            ->name('settings');
        Route::post('/settings/instagram', [InstagramConnectionController::class, 'store'])
            ->middleware('permission:manage settings')
            ->name('instagram.store');
        Route::delete('/settings/instagram', [InstagramConnectionController::class, 'destroy'])
            ->middleware('permission:manage settings')
            ->name('instagram.destroy');
        Route::get('/users', UsersManager::class)
            ->middleware(['permission:manage users', 'password.confirm'])
            ->name('users');
        Route::get('/users/{user}/content-permissions', ContentPermissionsEditor::class)
            ->middleware(['role:Super Admin', 'password.confirm'])
            ->name('users.content-permissions');
        Route::get('/audit', AuditLog::class)
            ->middleware('permission:view audit log')
            ->name('audit');
    });
});

Route::get('/news', NewsIndex::class)->name('news.index');
Route::get('/gallery', GalleryIndex::class)->name('gallery.index');
Route::get('/news/{post:slug}', NewsShow::class)->name('news.show');
Route::get('/players/{person:slug}', PlayerShow::class)->name('players.show');
Route::get('/games/{game:slug}', GameShow::class)->name('games.show');

Route::get('/', SitePage::class)->defaults('slug', 'home')->name('home');
Route::get('/{slug}', SitePage::class)
    ->whereIn('slug', ['about', 'roster', 'schedule', 'sponsors', 'contact'])
    ->name('site.page');
