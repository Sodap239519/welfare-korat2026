<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class UserPendingApproval extends Notification
{
    use Queueable;

    public function __construct(public User $pendingUser) {}

    public function via(object $notifiable): array
    {
        return ['database'];
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
}
