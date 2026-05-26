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

        // 1) LINE Notify (legacy)
        if ($token = config('services.line.notify_token')) {
            try {
                Http::asForm()
                    ->withToken($token)
                    ->timeout(10)
                    ->post('https://notify-api.line.me/api/notify', [
                        'message' => "\n" . $message,
                    ]);
                return; // ส่งแล้ว ไม่ต้องลองทางอื่น
            } catch (\Throwable $e) {
                Log::warning('LINE Notify failed: ' . $e->getMessage());
            }
        }

        // 2) LINE Messaging API push
        $msgToken = config('services.line.messaging_token');
        $target   = config('services.line.target_id');
        if ($msgToken && $target) {
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
    }
}
