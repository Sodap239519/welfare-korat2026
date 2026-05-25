<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|----------------------------------------------------------------------
| Scheduled jobs (Asia/Bangkok)
|----------------------------------------------------------------------
| ต้องตั้ง Plesk cron / system cron ทุก 1 นาที:
|   * * * * * cd /path-to-app && php artisan schedule:run >> /dev/null 2>&1
*/

// สรุปยอดรายหมู่บ้านประจำวัน — รันทุกวัน 16:30 (ตาม SOP)
Schedule::command('reports:daily-snapshot')
    ->dailyAt('16:30')
    ->timezone('Asia/Bangkok')
    ->withoutOverlapping()
    ->onSuccess(fn () => info('[Schedule] daily-snapshot done'))
    ->onFailure(fn () => info('[Schedule] daily-snapshot FAILED'));

// วิเคราะห์ Bottleneck รายสัปดาห์ — รันทุกวันจันทร์ 06:00
Schedule::command('reports:weekly-bottleneck')
    ->weeklyOn(1, '06:00')
    ->timezone('Asia/Bangkok')
    ->withoutOverlapping();
