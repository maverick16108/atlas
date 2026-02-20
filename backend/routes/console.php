<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-transition auction statuses every minute
Schedule::command('auctions:transition-expired')->everyMinute();

// Send auction start reminders and completion notifications every minute
Schedule::command('auctions:send-reminders')->everyMinute();
