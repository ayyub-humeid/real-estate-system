<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('crm:send-task-reminders')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('reservations:expire-due')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('reservations:notify-expiring')->dailyAt('08:00')->withoutOverlapping();
Schedule::command('contract-finance:send-installment-reminders')->daily()->withoutOverlapping();
Schedule::command('contracts:notify-overdue-leases')->daily()->withoutOverlapping();

// Daily database and application file backups to S3/R2
Schedule::command('backup:run')->dailyAt('02:00')->withoutOverlapping();

// Monitor backup health daily to ensure backups are current and within size limits
Schedule::command('backup:monitor')->dailyAt('03:00')->withoutOverlapping();
