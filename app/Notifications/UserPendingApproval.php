<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\LineChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class UserPendingApproval extends Notification
{
    use Queueable;

    /** ใช้ LINE_ADMIN_TARGET_ID — กลุ่ม Admin (แยกจาก report) */
    public string $lineTargetType = 'admin';

    public function __construct(public User $pendingUser) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];
        // เปิด LINE channel ก็ต่อเมื่อมี token configured — ไม่งั้นแค่ bell
        if (config('services.line.notify_token') || config('services.line.messaging_token')) {
            $channels[] = LineChannel::class;
        }
        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'    => 'user_pending',
            'user_id' => $this->pendingUser->id,
            'title'   => 'มีผู้ใช้รออนุมัติ',
            'message' => "{$this->pendingUser->name} ({$this->pendingUser->phone}) สมัครและรออนุมัติ",
            'icon'    => 'fi-rr-user-add',
            'color'   => 'blue',
            'url'     => '/admin/users',
        ];
    }

    /** ข้อความสำหรับส่งเข้า LINE (LineChannel จะเรียก method นี้) */
    public function toLine(object $notifiable): string
    {
        $u = $this->pendingUser;
        $position = $u->position_type
            ? "\nตำแหน่ง: {$u->position_type}" . ($u->position_other ? " ({$u->position_other})" : '')
            : '';

        return "🔔 มีผู้ใช้รออนุมัติ — Welfare Korat\n"
            . "ชื่อ: {$u->name}\n"
            . "เบอร์: {$u->phone}"
            . $position . "\n"
            . "เวลา: " . now()->format('d/m/Y H:i') . "\n"
            . "เข้าระบบเพื่ออนุมัติ: " . config('app.url') . "/admin/users";
    }
}
