<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduled maintenance tasks
Schedule::command('devices:mark-stale')->everyFiveMinutes();
Schedule::command('heartbeats:prune')->daily();
Schedule::command('tunnel-logs:prune')->weekly();
Schedule::command('tunnels:clean-orphaned')->hourly();
Schedule::command('tunnel:health-check')->everyThreeMinutes();
