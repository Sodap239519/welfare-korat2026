<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * LINE Bot Webhook — รับ event จาก LINE Bot
 * วัตถุประสงค์หลัก: จับ group ID / user ID อัตโนมัติเมื่อ bot ถูกเชิญเข้ากลุ่ม
 *                หรือเมื่อมีคนส่งข้อความถึง bot
 *
 * URL ที่ตั้งใน LINE Developer Console:
 *   https://<your-public-url>/api/line/webhook
 *
 * ผลที่ได้: ทุกข้อความ/event ที่เข้ามาจะ log เป็น JSON file
 *           ดูได้ที่ storage/app/line-events.json
 */
class LineWebhookController extends Controller
{
    /**
     * รับ webhook event จาก LINE
     */
    public function handle(Request $request): JsonResponse
    {
        $events = $request->input('events', []);

        foreach ($events as $ev) {
            $sourceType = $ev['source']['type'] ?? 'unknown';   // user / group / room
            $entry = [
                'time'      => now()->toDateTimeString(),
                'event'     => $ev['type'] ?? 'unknown',
                'source'    => $sourceType,
                'userId'    => $ev['source']['userId']  ?? null,
                'groupId'   => $ev['source']['groupId'] ?? null,
                'roomId'    => $ev['source']['roomId']  ?? null,
                'message'   => $ev['message']['text']   ?? null,
            ];

            // 1) Log ลง laravel.log (สำหรับ debug)
            Log::channel('single')->info('[LINE Webhook]', $entry);

            // 2) Append ลงไฟล์ JSON (ผู้ใช้อ่านง่าย)
            $this->appendToLog($entry);

            // 3) ถ้า bot ถูกเชิญเข้ากลุ่มใหม่ → ตอบกลับเป็นต้อนรับ + แสดง groupId
            if ($ev['type'] === 'join' && $sourceType === 'group') {
                $this->replyJoinMessage($ev);
            }
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * แสดงประวัติ events ที่จับได้ — เปิดเป็น URL ดูได้
     */
    public function show(): JsonResponse
    {
        $path = storage_path('app/line-events.json');
        if (!file_exists($path)) {
            return response()->json([
                'message' => 'ยังไม่มี event ที่จับได้',
                'tip'     => 'Add bot เป็นเพื่อน หรือเชิญเข้ากลุ่ม แล้วส่งข้อความถึง bot',
            ]);
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $entries = array_map(fn ($l) => json_decode($l, true), array_reverse(array_slice($lines, -50)));
        return response()->json([
            'count' => count($entries),
            'tip'   => 'ID ที่ขึ้นต้นด้วย U... คือ userId · C... คือ groupId · R... คือ roomId',
            'events' => $entries,
        ]);
    }

    private function appendToLog(array $entry): void
    {
        $path = storage_path('app/line-events.json');
        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    }

    /**
     * ตอบกลับเมื่อ bot ถูกเชิญเข้ากลุ่มใหม่ — แสดง groupId เพื่อให้ผู้ใช้ copy
     */
    private function replyJoinMessage(array $event): void
    {
        $token = config('services.line.messaging_token');
        $replyToken = $event['replyToken'] ?? null;
        $groupId = $event['source']['groupId'] ?? null;

        if (!$token || !$replyToken || !$groupId) return;

        $text = "🤖 สวัสดีครับ ผมคือ Bot ของระบบ Welfare Korat 2026\n\n"
            . "📋 Group ID นี้:\n{$groupId}\n\n"
            . "Copy ID นี้ไปใส่ใน .env เป็น LINE_ADMIN_TARGET_ID หรือ LINE_REPORT_TARGET_ID";

        try {
            \Illuminate\Support\Facades\Http::withToken($token)
                ->timeout(10)
                ->post('https://api.line.me/v2/bot/message/reply', [
                    'replyToken' => $replyToken,
                    'messages'   => [['type' => 'text', 'text' => $text]],
                ]);
        } catch (\Throwable $e) {
            Log::warning('LINE reply failed: ' . $e->getMessage());
        }
    }
}
