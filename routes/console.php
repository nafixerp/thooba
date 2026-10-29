<?php

use App\Services\SchemeService;
use App\Services\DueNotificationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('scheme:mark-overdue', function (SchemeService $service) {
    $n = $service->markOverdue();
    $this->info($n.' installment(s) marked as overdue.');
})->purpose('Mark past-due installments as OVERDUE');

Artisan::command('scheme:send-due-reminders {--days=}', function (DueNotificationService $service) {
    $days = $this->option('days');
    $n = $service->generate($days === null ? null : (int) $days);
    $this->info($n.' due reminder(s) created.');
})->purpose('Create in-app reminders for installments due soon');

Schedule::command('scheme:mark-overdue')->dailyAt('01:00');
Schedule::command('scheme:send-due-reminders')->dailyAt('09:00');
