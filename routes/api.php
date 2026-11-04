<?php

use App\Http\Controllers\Api\DbSyncController;
use App\Http\Middleware\VerifyDbSyncToken;
use Illuminate\Support\Facades\Route;

Route::prefix('db-sync')
    ->middleware([VerifyDbSyncToken::class, 'throttle:20,1'])
    ->group(function () {
        Route::get('status', [DbSyncController::class, 'status']);
        Route::get('export', [DbSyncController::class, 'export']);
        Route::post('import', [DbSyncController::class, 'import']);
    });
