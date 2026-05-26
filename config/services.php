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
        // ทางที่ 1 — LINE Notify (legacy แต่ง่ายสุด ถ้ายังมี token เดิมใช้ได้)
        'notify_token'    => env('LINE_NOTIFY_TOKEN'),
        // ทางที่ 2 — LINE Messaging API (ใหม่)
        'messaging_token' => env('LINE_MESSAGING_TOKEN'),
        'target_id'       => env('LINE_TARGET_ID'), // groupId / userId / roomId ที่จะ push ไป
    ],

];
