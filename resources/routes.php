<?php

use EliteDevSquad\SidecarExtensionBridge\Http\Controllers\{ExecuteCommandController,
    ExecuteFakeClockController,
    ExecuteTinkerController,
    GetSidecarDataController,
    LoginAsUserController,
    SetSidecarTokenController};
use Illuminate\Support\Facades\Route;

if (! app()->isProduction()) {
    Route::prefix('__devsquad-sidecar')->middleware(['web'])->group(function () {
        Route::post('/token', SetSidecarTokenController::class)
            ->name('devsquad-sidecar.token');

        Route::middleware('devsquad-sidecar-auth')->group(function () {
            Route::get('/data', GetSidecarDataController::class);
            Route::post('/login-as', LoginAsUserController::class);
            Route::post('/execute-command', ExecuteCommandController::class);
            Route::post('/execute-tinker', ExecuteTinkerController::class);
            Route::post('/execute-fake-clock', ExecuteFakeClockController::class);
        });
    });
}
