<?php

namespace App\Notifications;

use App\Models\DocumentBatch;
use App\Notifications\Channels\LineChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * ⛔ batch.rejected — ธนาคารปฏิเสธ batch (พร้อมเหตุผล)
 * → in-app bell ของ tracker · LINE ของกลุ่ม admin
 */
class BatchRejected extends Notification
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
            'type'     => 'batch_rejected',
            'batch_id' => $b->id,
            'title'    => "⛔ batch ของคุณถูกปฏิเสธ · #{$b->batch_no}",
            'message'  => "เหตุผล: " . mb_substr((string) $b->reject_reason, 0, 100),
            'icon'     => 'fi-rr-cross-circle',
            'color'    => 'red',
            'url'      => "/batches/{$b->id}",
        ];
    }

    public function toLine(object $notifiable): string
    {
        $b = $this->batch;
        return "⛔ batch ถูกปฏิเสธ\n"
            . "batch: #{$b->batch_no}\n"
            . "ปฏิเสธโดย: {$b->channel?->name} " . strtoupper((string) $b->sub_channel) . "\n"
            . "เหตุผล: {$b->reject_reason}\n"
            . "จำนวน: {$b->targets_count} ราย (รายชื่อกลับสถานะเดิม)\n"
            . "ผู้ส่ง: {$b->tracker?->name}\n"
            . "เวลา: " . now()->format('d/m/Y H:i');
    }
}
