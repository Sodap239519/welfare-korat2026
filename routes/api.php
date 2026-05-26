<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\MapController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ReferenceController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TargetController;
use App\Http\Controllers\Api\TrackerController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login',    [AuthController::class, 'login']);

    // Public geo lookups — สำหรับใช้ใน register form (เลือกหมู่บ้านที่รับผิดชอบ)
    Route::get('geo/amphurs',       [ReferenceController::class, 'amphurs']);
    Route::get('geo/tambons',       [ReferenceController::class, 'tambons']);
    Route::get('geo/villages',      [ReferenceController::class, 'villages']);
    Route::get('geo/village-names', [ReferenceController::class, 'villageNames']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout',  [AuthController::class, 'logout']);
        Route::get('me',       [AuthController::class, 'me']);
        Route::patch('profile',[AuthController::class, 'updateProfile']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    // Dashboard
    Route::prefix('dashboard')->controller(DashboardController::class)->group(function () {
        Route::get('stats',         'stats');
        Route::get('trends',        'trends');
        Route::get('by-channel',    'byChannel');
        Route::get('top-villages',  'topVillages');
        Route::get('top',           'top'); // ?level=amphur|tambon|village&limit=10
    });

    // Targets
    Route::get('targets',                  [TargetController::class, 'index']);
    Route::post('targets',                 [TargetController::class, 'store']);    // manual add (Admin/Tracker)
    Route::post('targets/bulk-status',     [TargetController::class, 'bulkUpdateStatus']);
    Route::get('targets/{id}',                 [TargetController::class, 'show'])->whereNumber('id');
    Route::patch('targets/{id}',               [TargetController::class, 'update'])->whereNumber('id');
    Route::patch('targets/{id}/status',        [TargetController::class, 'updateStatus'])->whereNumber('id');
    Route::get('targets/{id}/income-history',  [TargetController::class, 'incomeHistory'])->whereNumber('id');

    // Import
    Route::post('import/preview',  [ImportController::class, 'preview']);
    Route::post('import/run',      [ImportController::class, 'run']);
    Route::get('import/logs',      [ImportController::class, 'logs']);
    Route::get('import/logs/{id}', [ImportController::class, 'showLog'])->whereNumber('id');

    // Map
    Route::get('map/villages', [MapController::class, 'villages']);

    // Notifications
    Route::get('notifications',                 [NotificationController::class, 'index']);
    Route::get('notifications/unread-count',    [NotificationController::class, 'unreadCount']);
    Route::post('notifications/{id}/read',      [NotificationController::class, 'markRead']);
    Route::post('notifications/read-all',       [NotificationController::class, 'markAllRead']);

    // Reports
    Route::get('reports/daily-villages',        [ReportController::class, 'dailyVillages']);
    Route::get('reports/daily-villages/export', [ReportController::class, 'exportDailyVillages']);
    Route::get('reports/bottleneck',            [ReportController::class, 'bottleneck']);

    // Admin (super_admin only — enforced at frontend via meta.roles, basic protection)
    Route::middleware('role:super_admin')->prefix('admin')->group(function () {
        Route::get('users',                    [AdminUserController::class, 'index']);
        Route::get('users/stats',              [AdminUserController::class, 'stats']);
        Route::post('users',                   [AdminUserController::class, 'store']);
        Route::patch('users/{id}',             [AdminUserController::class, 'update'])->whereNumber('id');
        Route::post('users/{id}/approve',      [AdminUserController::class, 'approve'])->whereNumber('id');
        Route::post('users/{id}/suspend',      [AdminUserController::class, 'suspend'])->whereNumber('id');
        Route::delete('users/{id}',            [AdminUserController::class, 'destroy'])->whereNumber('id');

        Route::get('activity',       [ActivityLogController::class, 'index']);
        Route::get('activity/stats', [ActivityLogController::class, 'stats']);

        // SOP phase — Super Admin CRUD + set-current
        Route::post('phases',                  [ReferenceController::class, 'storePhase']);
        Route::patch('phases/{id}',            [ReferenceController::class, 'updatePhase'])->whereNumber('id');
        Route::delete('phases/{id}',           [ReferenceController::class, 'destroyPhase'])->whereNumber('id');
        Route::post('phases/{id}/set-current', [ReferenceController::class, 'setCurrentPhase'])->whereNumber('id');

        // Settings — Channels / Statuses / Banks (Super Admin)
        Route::controller(\App\Http\Controllers\Api\SettingsController::class)->group(function () {
            Route::get('channels',          'listChannels');
            Route::post('channels',         'storeChannel');
            Route::patch('channels/{id}',   'updateChannel')->whereNumber('id');
            Route::delete('channels/{id}',  'destroyChannel')->whereNumber('id');

            Route::get('statuses',          'listStatuses');
            Route::post('statuses',         'storeStatus');
            Route::patch('statuses/{id}',   'updateStatus')->whereNumber('id');
            Route::delete('statuses/{id}',  'destroyStatus')->whereNumber('id');

            Route::get('banks',             'listBanks');
            Route::post('banks',            'storeBank');
            Route::patch('banks/{id}',      'updateBank')->whereNumber('id');
            Route::delete('banks/{id}',     'destroyBank')->whereNumber('id');
        });
    });

    // Trackers — อ่านได้ทุก role · เขียน/แก้ไข/ลบ + เปิดบัญชี เฉพาะ super_admin
    Route::get('trackers',        [TrackerController::class, 'index']);
    Route::middleware('role:super_admin')->group(function () {
        Route::post('trackers',                   [TrackerController::class, 'store']);
        Route::patch('trackers/{id}',             [TrackerController::class, 'update'])->whereNumber('id');
        Route::delete('trackers/{id}',            [TrackerController::class, 'destroy'])->whereNumber('id');
        Route::post('trackers/{id}/create-user',  [TrackerController::class, 'createUser'])->whereNumber('id');

        // Map pin editing
        Route::patch('villages/{id}/coords', [\App\Http\Controllers\Api\VillageController::class, 'updateCoords'])->whereNumber('id');
    });

    // Lookups
    Route::get('ref/statuses',         [ReferenceController::class, 'statuses']);
    Route::get('ref/channels',         [ReferenceController::class, 'channels']);
    Route::get('ref/banks',            [ReferenceController::class, 'banks']);
    Route::get('ref/amphurs',          [ReferenceController::class, 'amphurs']);
    Route::get('ref/tambons',          [ReferenceController::class, 'tambons']);
    Route::get('ref/villages',         [ReferenceController::class, 'villages']);
    Route::get('ref/project-phases',   [ReferenceController::class, 'projectPhases']);
    Route::get('ref/overview-metrics', [ReferenceController::class, 'overviewMetrics']);
});
