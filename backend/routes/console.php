<?php

use App\Services\Integrations\Kiot\KiotConfigurationResolver;
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
    ->when(fn () => app(KiotConfigurationResolver::class)->resolve()->productSyncEnabled);

Schedule::command('kiot:retry-outbox')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer()
    ->when(fn () => app(KiotConfigurationResolver::class)->resolve()->orderSyncEnabled);
