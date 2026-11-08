<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
|
| The server runs `php artisan schedule:run` every minute (systemd timer or
| cron). Keep long running work on the queue, not in the scheduler.
|
*/

// Retention: remove expired generated documents and their private files.
Schedule::command('documents:prune')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->onOneServer()
    ->description('حذف اسناد منقضی‌شده و فایل‌های خصوصی آن‌ها');

// Operational health snapshot (DB, storage write test, Gotenberg, queue).
Schedule::command('pdf:health --json')
    ->hourly()
    ->withoutOverlapping()
    ->description('بررسی سلامت سرویس، Gotenberg و صف');

// Housekeeping of framework tables.
Schedule::command('queue:prune-failed --hours=168')
    ->daily()
    ->withoutOverlapping()
    ->description('پاک‌سازی Jobهای شکست‌خورده قدیمی‌تر از ۷ روز');
