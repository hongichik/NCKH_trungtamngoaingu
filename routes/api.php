<?php

use App\Http\Controllers\Api\DbSyncController;
use App\Http\Controllers\Api\FileSyncController;
use App\Http\Middleware\VerifyDbSyncToken;
use Illuminate\Support\Facades\Route;

Route::prefix('db-sync')->group(function () {
    Route::get('status', [DbSyncController::class, 'status'])->middleware(VerifyDbSyncToken::class.':any');

    // Đồng bộ CSDL
    Route::middleware(VerifyDbSyncToken::class.':db')->group(function () {
        Route::get('export', [DbSyncController::class, 'export']);
        Route::post('chunk', [DbSyncController::class, 'chunk']);
        Route::post('commit', [DbSyncController::class, 'commit']);
        Route::post('run', [DbSyncController::class, 'run'])->middleware('throttle:10,1');
    });

    // Đồng bộ thư mục
    Route::prefix('files')->middleware(VerifyDbSyncToken::class.':files')->group(function () {
        Route::get('manifest', [FileSyncController::class, 'manifest']);
        Route::post('put', [FileSyncController::class, 'put']);
        Route::get('get', [FileSyncController::class, 'get']);
        Route::post('delete', [FileSyncController::class, 'delete']);
        Route::post('report', [FileSyncController::class, 'report']);
    });
});
