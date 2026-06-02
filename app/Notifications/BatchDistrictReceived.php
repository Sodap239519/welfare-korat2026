<?php

namespace App\Notifications;

use App\Models\DocumentBatch;
use App\Notifications\Channels\LineChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * 🏢 batch.district_received — อำเภอยืนยันรับเอกสารจาก tracker
 * → in-app bell ของ tracker · LINE ของกลุ่ม admin
 */
class BatchDistrictReceived extends Notification
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
            'type'     => 'batch_district_received',
            'batch_id' => $b->id,
            'title'    => "🏢 อำเภอรับ batch ของคุณแล้ว · #{$b->batch_no}",
            'message'  => "อำเภอ{$b->targetAmphur?->name} รับเอกสาร {$b->targets_count} ราย · กำลังส่งต่อให้ธนาคาร",
            'icon'     => 'fi-rr-building',
            'color'    => 'indigo',
            'url'      => "/batches/{$b->id}",
        ];
    }

    public function toLine(object $notifiable): string
    {
        $b = $this->batch;
        return "🏢 อำเภอรับเอกสารแล้ว\n"
            . "batch: #{$b->batch_no}\n"
            . "อำเภอ: {$b->targetAmphur?->name}\n"
            . "ผู้รับ: {$b->districtReceivedBy?->name}\n"
            . "จำนวน: {$b->targets_count} ราย\n"
            . "ผู้ส่ง: {$b->tracker?->name}\n"
            . "เวลา: " . now()->format('d/m/Y H:i');
    }
}
