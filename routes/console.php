<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('cms:publish-scheduled')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('queue:work database --stop-when-empty --tries=3 --timeout=120')
    ->everyMinute()
    ->withoutOverlapping(10)
    ->runInBackground();

Schedule::command('cms:purge-expired')
    ->dailyAt('02:15')
    ->withoutOverlapping();

Schedule::command('cms:backup')
    ->dailyAt('02:30')
    ->withoutOverlapping();

Schedule::command('cms:monitor')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('activitylog:clean')
    ->monthly()
    ->withoutOverlapping();
