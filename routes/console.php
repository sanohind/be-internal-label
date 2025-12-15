<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule ERP sync to run daily at 23:55
Schedule::command('erp:sync')
->dailyAt('23:55')
->timezone('Asia/Jakarta')
->withoutOverlapping()
->runInBackground();
