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

// แจ้งเตือน tracker เมื่อ target ค้างเกิน 7 วัน — รัน 08:00 ทุกวัน
Schedule::command('reports:notify-stuck --days=7')
    ->dailyAt('08:00')
    ->timezone('Asia/Bangkok')
    ->withoutOverlapping();

// รายงานความคืบหน้าทุก 3 วัน (TOP 5 อำเภอ + ตำบลย่อย) → ส่งเข้า LINE
// รันวันที่ 1, 4, 7, 10, ... ของเดือน เวลา 08:00
Schedule::command('report:three-day')
    ->cron('0 8 1-31/3 * *')
    ->timezone('Asia/Bangkok')
    ->withoutOverlapping()
    ->onSuccess(fn () => info('[Schedule] three-day report sent'))
    ->onFailure(fn () => info('[Schedule] three-day report FAILED'));

// ล้าง activity_log เก่ากว่า 90 วัน (ตาม config) — กันตารางโตเรื่อยๆ · ตี 3 ทุกวัน
Schedule::command('activitylog:clean')
    ->dailyAt('03:00')
    ->timezone('Asia/Bangkok')
    ->withoutOverlapping();
