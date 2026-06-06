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
        $u = $this->pendingUser;
        $isStudent = !empty($u->student_id);
        return [
            'type'    => 'user_pending',
            'user_id' => $u->id,
            'title'   => $isStudent ? 'นักศึกษารออนุมัติ' : 'มีผู้ใช้รออนุมัติ',
            'message' => $isStudent
                ? "{$u->name} (นักศึกษา · {$u->faculty}) รออนุมัติ"
                : "{$u->name} ({$u->phone}) สมัครและรออนุมัติ",
            'icon'    => $isStudent ? 'fi-rr-graduation-cap' : 'fi-rr-user-add',
            'color'   => 'blue',
            'url'     => '/admin/users',
        ];
    }

    /** ข้อความสำหรับส่งเข้า LINE (LineChannel จะเรียก method นี้) */
    public function toLine(object $notifiable): string
    {
        $u = $this->pendingUser;

        // นักศึกษา — แสดงข้อมูลเฉพาะนักศึกษา
        if (!empty($u->student_id)) {
            $unit = $u->work_unit_type === 'bank'
                ? 'ธนาคาร: ' . strtoupper($u->bank_sub_channel ?? '-') . ' ' . ($u->bank_branch ?? '')
                : 'อำเภอ: ' . ($u->amphur?->name ?? '-');
            return "🎓 นักศึกษาสมัครใหม่ — Welfare Korat\n"
                . "ชื่อ: {$u->name}\n"
                . "รหัส นศ.: {$u->student_id}\n"
                . "คณะ/สาขา: {$u->faculty} / {$u->major}\n"
                . "เบอร์: {$u->phone}\n"
                . $unit . "\n"
                . "เวลา: " . now()->format('d/m/Y H:i') . "\n"
                . "เข้าระบบเพื่ออนุมัติ: " . config('app.url') . "/admin/users";
        }

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
