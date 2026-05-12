<?php

use EliteDevSquad\SidecarLaravel\Http\Controllers\{ClearUserCacheController,
    ExecuteFakeClockController,
    ExecuteTinkerController,
    GetSidecarDataController,
    LoginAsUserController,
    SidecarJsController};
use EliteDevSquad\SidecarLaravel\Http\Controllers\{ExecuteCommandController, ExecuteTinkerOnQueueController};
use Illuminate\Support\Facades\Route;

if (! app()->isProduction()) {
    Route::prefix('__devsquad-sidecar')->middleware(['web'])->group(function () {
        Route::get('/assets/js', SidecarJsController::class);
        Route::get('/data', GetSidecarDataController::class);
        Route::post('/login-as', LoginAsUserController::class);

        Route::middleware('devsquad-sidecar-auth')->group(function () {
            Route::post('/execute-command', ExecuteCommandController::class);
            Route::post('/execute-tinker', ExecuteTinkerController::class);
            Route::post('/execute-fake-clock', ExecuteFakeClockController::class);
            Route::post('/execute-tinker-on-queue', ExecuteTinkerOnQueueController::class);
            Route::post('/clear-user-cache', ClearUserCacheController::class);
        });
    });
}
