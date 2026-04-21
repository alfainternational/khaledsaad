<?php

use App\Http\Controllers\Api\V1\AdminFeatureFlagController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\PingController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\StudioGenerationController;
use App\Http\Controllers\Api\V1\TokenController;
use App\Http\Controllers\Api\V1\WorkspaceIndexController;
use App\Http\Controllers\Api\V1\WorkspaceToolController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| REST API — الإصدار 1 (Sanctum)
|--------------------------------------------------------------------------
| المسار الأساسي: /api/v1/...
| {workspace} و {project} = public_id (ليس المعرّف الداخلي).
| مصادقة المحميات: Authorization: Bearer {token}
| نطاق اختياري للتوكن: workspace_public_id عند POST /api/v1/tokens
| Idempotency (اختياري): رأس Idempotency-Key لطلبات POST المحددة
*/

Route::prefix('v1')->group(function (): void {
    Route::get('/ping', PingController::class)->middleware('throttle:60,1');

    Route::post('/tokens', [TokenController::class, 'store'])
        ->middleware('throttle:5,1');

    Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function (): void {
        Route::get('/me', MeController::class);
        Route::get('/workspaces', [WorkspaceIndexController::class, 'index']);

        Route::middleware('api.super_admin')->prefix('admin')->group(function (): void {
            Route::get('/feature-flags', [AdminFeatureFlagController::class, 'index']);
            Route::patch('/feature-flags/{key}', [AdminFeatureFlagController::class, 'update']);
        });

        Route::middleware('api.workspace')->prefix('workspaces/{workspace_public_id}')->group(function (): void {
            Route::get('/projects', [ProjectController::class, 'index']);
            Route::post('/projects', [ProjectController::class, 'store'])->middleware('idempotency:projects');

            Route::middleware('api.project')->prefix('projects/{project_public_id}')->group(function (): void {
                Route::get('/tools/{tcode}', [WorkspaceToolController::class, 'load']);
                Route::post('/tools/{tcode}/run', [WorkspaceToolController::class, 'run'])
                    ->middleware('idempotency:tool_run');
            });

            Route::post('/studio/generations', [StudioGenerationController::class, 'store'])
                ->middleware('idempotency:studio');
        });
    });
});
