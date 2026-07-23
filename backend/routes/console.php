<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new \App\Jobs\Integrations\Kiot\SyncKiotProducts)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->when(fn () => config('integrations.kiot.enabled') && config('integrations.kiot.product_sync_enabled'));

Schedule::command('kiot:retry-outbox')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer()
    ->when(fn () => config('integrations.kiot.enabled') && config('integrations.kiot.order_sync_enabled'));
