<?php

namespace App\Notifications;

use App\Models\DocumentBatch;
use App\Notifications\Channels\LineChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * ✅ batch.received — ธนาคารยืนยันรับเอกสารแล้ว
 * → in-app bell ของ tracker · LINE ของกลุ่ม admin
 */
class BatchReceived extends Notification
{
    use Queueable;

    public string $lineTargetType = 'admin';

    public function __construct(public DocumentBatch $batch, public bool $sendLine = false) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if ($this->sendLine && (config('services.line.notify_token') || config('services.line.messaging_token'))) {
            $channels[] = LineChannel::class;
        }
        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        $b = $this->batch;
        return [
            'type'     => 'batch_received',
            'batch_id' => $b->id,
            'title'    => "✅ ธนาคารรับ batch ของคุณแล้ว · #{$b->batch_no}",
            'message'  => "{$b->channel?->name} " . strtoupper((string) $b->sub_channel)
                          . " รับ {$b->targets_count} ราย · กำลังบันทึกข้อมูลลงระบบ",
            'icon'     => 'fi-rr-inbox-in',
            'color'    => 'sky',
            'url'      => "/batches/{$b->id}",
        ];
    }

    public function toLine(object $notifiable): string
    {
        $b = $this->batch;
        return "✅ ธนาคารรับเอกสารแล้ว\n"
            . "batch: #{$b->batch_no}\n"
            . "ผู้รับ: {$b->receivedBy?->name} · {$b->channel?->name} " . strtoupper((string) $b->sub_channel) . "\n"
            . "จำนวน: {$b->targets_count} ราย\n"
            . "ผู้ส่ง: {$b->tracker?->name}\n"
            . "เวลา: " . now()->format('d/m/Y H:i');
    }
}
