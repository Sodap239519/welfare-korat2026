<?php

namespace App\Notifications;

use App\Models\Target;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TargetStatusStuck extends Notification
{
    use Queueable;

    public function __construct(
        public Target $target,
        public string $statusCode,
        public int $daysStuck,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'target_stuck',
            'target_id'   => $this->target->id,
            'name'        => trim(($this->target->prefix ?? '').' '.$this->target->first_name.' '.($this->target->last_name ?? '')),
            'status_code' => $this->statusCode,
            'days_stuck'  => $this->daysStuck,
            'title'       => "เป้าหมายค้างที่สถานะ {$this->statusCode} เกิน {$this->daysStuck} วัน",
            'message'     => "ติดตาม {$this->target->first_name} {$this->target->last_name} ที่ยังไม่ขยับสถานะ",
            'icon'        => 'fi-rr-triangle-warning',
            'color'       => 'orange',
            'url'         => "/targets/{$this->target->id}",
        ];
    }
}
