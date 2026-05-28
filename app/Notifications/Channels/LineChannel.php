<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Notification channel ส่งข้อความเข้า LINE
 * - ลำดับ: LINE Notify (legacy, ง่ายสุด) → LINE Messaging API push
 * - notification class ต้องมี method toLine($notifiable): string
 * - fail silently ถ้าไม่ได้ตั้ง token (ไม่ทำให้ flow อื่นพัง)
 */
class LineChannel
{
    public function send($notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toLine')) return;

        $message = $notification->toLine($notifiable);
        if (empty($message)) return;

        // 1) LINE Notify (legacy · LINE ปิด service มี.ค. 2025 — fallback เท่านั้น)
        if ($token = config('services.line.notify_token')) {
            try {
                Http::asForm()
                    ->withToken($token)
                    ->timeout(10)
                    ->post('https://notify-api.line.me/api/notify', [
                        'message' => "\n" . $message,
                    ]);
                return;
            } catch (\Throwable $e) {
                Log::warning('LINE Notify failed: ' . $e->getMessage());
            }
        }

        // 2) LINE Messaging API push
        $msgToken = config('services.line.messaging_token');
        if (!$msgToken) return;

        // ระบุ target ตามประเภท notification:
        // - ถ้า notification class มี method getLineTarget() → ใช้ค่าจากตรงนั้น
        // - ถ้า notification class มี property $lineTargetType ('admin'/'report') → map เป็น config
        // - ถ้าไม่มี → fallback ไปที่ legacy target_id
        $target = $this->resolveTarget($notification);
        if (!$target) {
            Log::warning('LINE push skipped — no target_id configured for ' . get_class($notification));
            return;
        }

        try {
            Http::withToken($msgToken)
                ->timeout(10)
                ->post('https://api.line.me/v2/bot/message/push', [
                    'to' => $target,
                    'messages' => [['type' => 'text', 'text' => $message]],
                ]);
        } catch (\Throwable $e) {
            Log::warning('LINE Messaging push failed: ' . $e->getMessage());
        }
    }

    /**
     * หา target ID ที่จะส่งให้ — ตามประเภทของ notification
     */
    private function resolveTarget(Notification $notification): ?string
    {
        // 1) method override ใน notification class (มีอำนาจสูงสุด)
        if (method_exists($notification, 'getLineTarget')) {
            $t = $notification->getLineTarget();
            if (!empty($t)) return $t;
        }

        // 2) property $lineTargetType บน notification class
        $type = property_exists($notification, 'lineTargetType')
            ? $notification->lineTargetType
            : null;

        if ($type === 'admin') {
            return config('services.line.admin_target_id') ?: config('services.line.target_id');
        }
        if ($type === 'report') {
            return config('services.line.report_target_id') ?: config('services.line.target_id');
        }

        // 3) fallback — legacy target_id
        return config('services.line.target_id');
    }
}
