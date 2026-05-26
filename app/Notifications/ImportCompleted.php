<?php

namespace App\Notifications;

use App\Models\ImportLog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ImportCompleted extends Notification
{
    use Queueable;

    public function __construct(public ImportLog $log) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $hasErrors = $this->log->failed > 0;
        return [
            'type'    => 'import_completed',
            'log_id'  => $this->log->id,
            'title'   => $hasErrors
                ? "นำเข้าไฟล์เสร็จ มีบางรายการผิดพลาด"
                : "นำเข้าไฟล์เสร็จ {$this->log->filename}",
            'message' => sprintf(
                '%s · เพิ่มใหม่ %d · อัปเดต %d · ผิดพลาด %d',
                $this->log->filename, $this->log->success, $this->log->updated, $this->log->failed
            ),
            'icon'    => $hasErrors ? 'fi-rr-triangle-warning' : 'fi-rr-check-circle',
            'color'   => $hasErrors ? 'orange' : 'green',
            'url'     => '/import',
        ];
    }
}
