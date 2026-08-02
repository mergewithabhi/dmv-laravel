<?php

namespace App\Providers;

use App\Contracts\NewsletterProvider;
use App\Services\Newsletter\BrevoNewsletterProvider;
use App\Services\Newsletter\LogNewsletterProvider;
use App\Services\Newsletter\MailchimpNewsletterProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleRequests\EndpointResolver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(NewsletterProvider::class, function () {
            return match (config('services.newsletter.driver')) {
                'mailchimp' => app(MailchimpNewsletterProvider::class),
                'brevo' => app(BrevoNewsletterProvider::class),
                default => app(LogNewsletterProvider::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(Vite $vite): void
    {
        Model::shouldBeStrict(! app()->isProduction());
        $vite->usePreloadTagAttributes(false);

        if (config('livewire.csp_safe')) {
            Livewire::setScriptRoute(
                fn ($handle) => Route::get(
                    EndpointResolver::prefix().'/livewire.csp.js',
                    $handle
                )
            );
        }
    }
}
