<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ReferenceController;
use App\Http\Controllers\Api\TargetController;
use App\Http\Controllers\Api\TrackerController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login',    [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me',      [AuthController::class, 'me']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    // Dashboard
    Route::prefix('dashboard')->controller(DashboardController::class)->group(function () {
        Route::get('stats',         'stats');
        Route::get('trends',        'trends');
        Route::get('by-channel',    'byChannel');
        Route::get('top-villages',  'topVillages');
    });

    // Targets
    Route::get('targets',                  [TargetController::class, 'index']);
    Route::get('targets/{id}',             [TargetController::class, 'show'])->whereNumber('id');
    Route::patch('targets/{id}/status',    [TargetController::class, 'updateStatus'])->whereNumber('id');

    // Trackers
    Route::get('trackers',        [TrackerController::class, 'index']);
    Route::post('trackers',       [TrackerController::class, 'store']);
    Route::patch('trackers/{id}', [TrackerController::class, 'update'])->whereNumber('id');
    Route::delete('trackers/{id}',[TrackerController::class, 'destroy'])->whereNumber('id');

    // Lookups
    Route::get('ref/statuses',         [ReferenceController::class, 'statuses']);
    Route::get('ref/channels',         [ReferenceController::class, 'channels']);
    Route::get('ref/amphurs',          [ReferenceController::class, 'amphurs']);
    Route::get('ref/tambons',          [ReferenceController::class, 'tambons']);
    Route::get('ref/villages',         [ReferenceController::class, 'villages']);
    Route::get('ref/project-phases',   [ReferenceController::class, 'projectPhases']);
    Route::get('ref/overview-metrics', [ReferenceController::class, 'overviewMetrics']);
});
