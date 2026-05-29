<?php

namespace App\Notifications;

use App\Models\DocumentBatch;
use App\Notifications\Channels\LineChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * 🎉 batch.recorded — ธนาคารบันทึกข้อมูลลงระบบครบแล้ว (= ปิด 4.2.4)
 * → in-app bell ของ tracker · LINE ของกลุ่ม admin
 */
class BatchRecorded extends Notification
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
            'type'     => 'batch_recorded',
            'batch_id' => $b->id,
            'title'    => "🎉 batch ของคุณบันทึกครบแล้ว · #{$b->batch_no}",
            'message'  => "{$b->targets_count} ราย เข้าสู่ขั้นรอยืนยันตัวตน (KYC) ต่อ",
            'icon'     => 'fi-rr-check-double',
            'color'    => 'green',
            'url'      => "/batches/{$b->id}",
        ];
    }

    public function toLine(object $notifiable): string
    {
        $b = $this->batch;
        return "🎉 batch บันทึกข้อมูลครบแล้ว\n"
            . "batch: #{$b->batch_no}\n"
            . "ผู้บันทึก: {$b->recordedBy?->name} · {$b->channel?->name} " . strtoupper((string) $b->sub_channel) . "\n"
            . "จำนวน: {$b->targets_count} ราย → รอ KYC ต่อ\n"
            . "ผู้ส่ง: {$b->tracker?->name}\n"
            . "เวลา: " . now()->format('d/m/Y H:i');
    }
}
