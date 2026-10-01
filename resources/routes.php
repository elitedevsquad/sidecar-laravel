<?php

use EliteDevSquad\SidecarLaravel\Http\Controllers\{ActivityController,
    ClearUserCacheController,
    ExecuteFakeClockController,
    ExecuteTinkerController,
    GetSidecarDataController,
    LoginAsRedirectController,
    LoginAsUserController,
    PresenceController,
    SidecarJsController};
use EliteDevSquad\SidecarLaravel\Http\Controllers\{ExecuteCommandController, ExecuteTinkerOnQueueController, GetLogsController, ListCommandsController, ListUsersController};
use EliteDevSquad\SidecarLaravel\Http\Middleware\RecordActivity;
use Illuminate\Support\Facades\Route;

if (! app()->isProduction()) {
    Route::prefix('__devsquad-sidecar')->middleware(['web'])->group(function () {
        Route::get('/assets/js', SidecarJsController::class);
        Route::get('/data', GetSidecarDataController::class);
        Route::post('/login-as', LoginAsUserController::class)->middleware(RecordActivity::class);
        Route::get('/login-as/{user}', LoginAsRedirectController::class)->name('devsquad-sidecar.login-as');

        Route::middleware('devsquad-sidecar-auth')->group(function () {
            Route::get('/commands', ListCommandsController::class)->name('devsquad-sidecar.commands');
            Route::get('/logs', GetLogsController::class)->name('devsquad-sidecar.logs');
            Route::get('/users', ListUsersController::class)->name('devsquad-sidecar.users');
            Route::post('/presence', [PresenceController::class, 'store']);
            Route::delete('/presence', [PresenceController::class, 'destroy']);
            Route::get('/activity', [ActivityController::class, 'index']);
            Route::get('/activity/{id}', [ActivityController::class, 'show']);
            Route::post('/execute-command', ExecuteCommandController::class)->middleware(RecordActivity::class);
            Route::post('/execute-tinker', ExecuteTinkerController::class)->middleware(RecordActivity::class);
            Route::post('/execute-fake-clock', ExecuteFakeClockController::class)->middleware(RecordActivity::class);
            Route::post('/execute-tinker-on-queue', ExecuteTinkerOnQueueController::class)->middleware(RecordActivity::class);
            Route::post('/clear-user-cache', ClearUserCacheController::class)->middleware(RecordActivity::class);
        });
    });
}
