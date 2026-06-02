<?php

namespace App\Notifications;

use App\Models\DocumentBatch;
use App\Notifications\Channels\LineChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * 📨 batch.forwarded_to_bank — อำเภอส่งเอกสารต่อให้ธนาคาร
 * → in-app bell ของ bank_staff + tracker · LINE ของกลุ่ม admin
 */
class BatchForwardedToBank extends Notification
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
        $bankCode = strtoupper((string) $b->forwarded_to_sub_channel);
        return [
            'type'     => 'batch_forwarded_to_bank',
            'batch_id' => $b->id,
            'title'    => "📨 batch ใหม่ถูกส่งต่อให้ธนาคาร · #{$b->batch_no}",
            'message'  => "อำเภอ{$b->targetAmphur?->name} ส่ง batch {$b->targets_count} ราย ให้ {$b->forwardedToChannel?->name} {$bankCode}",
            'icon'     => 'fi-rr-paper-plane',
            'color'    => 'amber',
            'url'      => "/batches/{$b->id}",
        ];
    }

    public function toLine(object $notifiable): string
    {
        $b = $this->batch;
        $bankCode = strtoupper((string) $b->forwarded_to_sub_channel);
        return "📨 อำเภอส่งต่อเอกสารให้ธนาคาร\n"
            . "batch: #{$b->batch_no}\n"
            . "อำเภอ{$b->targetAmphur?->name} → {$b->forwardedToChannel?->name} {$bankCode}\n"
            . "ส่งโดย: {$b->forwardedBy?->name}\n"
            . "จำนวน: {$b->targets_count} ราย\n"
            . "ผู้ติดตามต้นทาง: {$b->tracker?->name}\n"
            . "เวลา: " . now()->format('d/m/Y H:i');
    }
}
