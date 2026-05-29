<?php

namespace App\Notifications;

use App\Models\DocumentBatch;
use App\Notifications\Channels\LineChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * 📦 batch.submitted — tracker ส่ง batch ให้ธนาคารแล้ว
 * → in-app bell ของ bank_staff/admin · LINE ของกลุ่ม admin
 */
class BatchSubmitted extends Notification
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
            'type'     => 'batch_submitted',
            'batch_id' => $b->id,
            'title'    => "📦 batch ใหม่ส่งเข้ามา · #{$b->batch_no}",
            'message'  => "{$b->tracker?->name} ส่ง {$b->targets_count} ราย ที่ {$b->channel?->name} "
                          . strtoupper((string) $b->sub_channel),
            'icon'     => 'fi-rr-paper-plane',
            'color'    => 'amber',
            'url'      => "/batches/{$b->id}",
        ];
    }

    public function toLine(object $notifiable): string
    {
        $b = $this->batch;
        $tracker = $b->tracker?->name ?? '—';
        $position = $b->submitter_name ?: $this->submitterRoleLabel($b->submitter_role);
        return "📦 batch ใหม่ส่งเข้ามา\n"
            . "เลขที่: #{$b->batch_no}\n"
            . "ผู้ส่ง: {$tracker} ({$position})\n"
            . "ปลายทาง: {$b->channel?->name} " . strtoupper((string) $b->sub_channel) . "\n"
            . "จำนวน: {$b->targets_count} ราย\n"
            . "เวลา: " . now()->format('d/m/Y H:i') . "\n"
            . "ดูรายละเอียด: " . config('app.url') . "/batches/{$b->id}";
    }

    private function submitterRoleLabel(?string $role): string
    {
        return DocumentBatch::submitterRoleLabels()[$role] ?? '—';
    }
}
