<?php

use Illuminate\Support\Facades\Route;

// SPA catch-all — all routes return app.blade.php and Vue Router handles them
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!api).*$');
