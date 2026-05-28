<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'line' => [
        // ทางที่ 1 — LINE Notify (legacy · LINE ปิด service มี.ค. 2025 ใช้ไม่ได้แล้ว)
        'notify_token'    => env('LINE_NOTIFY_TOKEN'),

        // ทางที่ 2 — LINE Messaging API (Bot) — 1 token ส่งไปหลายกลุ่มได้
        'messaging_token' => env('LINE_MESSAGING_TOKEN'),

        // target IDs (groupId / userId / roomId) — แยกตามประเภทการแจ้งเตือน
        'admin_target_id'  => env('LINE_ADMIN_TARGET_ID'),   // กลุ่ม Admin — แจ้งสมัครใหม่ + อนุมัติ
        'report_target_id' => env('LINE_REPORT_TARGET_ID'),  // กลุ่มรายงาน — Report 3 วัน + bottleneck
        'target_id'        => env('LINE_TARGET_ID'),          // legacy fallback (ใช้ถ้าไม่ได้ระบุ admin/report)
    ],

];
