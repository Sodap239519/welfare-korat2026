<?php

namespace App\Notifications;

use App\Notifications\Channels\LineChannel;
use Illuminate\Notifications\Notification;

/**
 * รายงานความคืบหน้า — ส่งเข้า LINE (และ database log)
 * ใช้กับ Notification::route('line', true) ก็ได้ (ไม่ต้องผูกกับ user)
 */
class ProgressReport extends Notification
{
    /** ใช้ LINE_REPORT_TARGET_ID — กลุ่มรายงาน (แยกจาก admin) */
    public string $lineTargetType = 'report';

    public function __construct(public string $message) {}

    public function via(object $notifiable): array
    {
        return [LineChannel::class];
    }

    public function toLine(object $notifiable): string
    {
        return $this->message;
    }
}
