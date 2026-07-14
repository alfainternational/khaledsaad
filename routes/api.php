<?php

use App\Http\Controllers\Api\V1\AccountAiKeyController;
use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AdminFeatureFlagController;
use App\Http\Controllers\Api\V1\AgencyBrandingController;
use App\Http\Controllers\Api\V1\AiAssistController;
use App\Http\Controllers\Api\AiConversationController;
use App\Http\Controllers\Api\V1\ApprovalController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\DeviceTokenController;
use App\Http\Controllers\Api\V1\ExecutionPackageController;
use App\Http\Controllers\Api\V1\KnowledgeUploadController;
use App\Http\Controllers\Api\V1\KnowledgeUploadSessionController;
use App\Http\Controllers\Api\V1\LogoutController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\OnboardingController;
use App\Http\Controllers\Api\V1\PasswordController;
use App\Http\Controllers\Api\V1\PingController;
use App\Http\Controllers\Api\V1\PrivateWorkerController;
use App\Http\Controllers\Api\V1\ProjectAuditController;
use App\Http\Controllers\Api\V1\ProjectBriefController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\ProjectRecommendationController;
use App\Http\Controllers\Api\V1\ProjectReportController;
use App\Http\Controllers\Api\V1\RegisterController;
use App\Http\Controllers\Api\V1\SocialAuthController;
use App\Http\Controllers\Api\V1\StudioGenerationController;
use App\Http\Controllers\Api\V1\StudioTemplateController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\TokenController;
use App\Http\Controllers\Api\V1\ToolIndexController;
use App\Http\Controllers\Api\V1\WorkspaceDashboardController;
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
    Route::middleware(['private.worker', 'throttle:120,1'])
        ->prefix('private-worker')
        ->group(function (): void {
            Route::post('/lease', [PrivateWorkerController::class, 'lease']);
            Route::post('/jobs/{jobPublicId}/heartbeat', [PrivateWorkerController::class, 'heartbeat']);
            Route::get('/jobs/{jobPublicId}/input', [PrivateWorkerController::class, 'input']);
            Route::post('/jobs/{jobPublicId}/complete', [PrivateWorkerController::class, 'complete']);
            Route::post('/jobs/{jobPublicId}/fail', [PrivateWorkerController::class, 'fail']);
        });

    Route::get('/ping', PingController::class)->middleware('throttle:60,1');

    // المحتوى العام (تجربة الضيف في التطبيق): قراءة فقط، مُكاش، بلا مصادقة.
    Route::get('/public/overview', [\App\Http\Controllers\Api\V1\PublicContentController::class, 'overview'])
        ->middleware('throttle:30,1');

    Route::post('/tokens', [TokenController::class, 'store'])
        ->middleware('throttle:5,1');

    Route::post('/register', [RegisterController::class, 'store'])
        ->middleware('throttle:5,1');

    Route::post('/password/forgot', [PasswordController::class, 'forgot'])
        ->middleware('throttle:5,1');
    Route::post('/password/reset', [PasswordController::class, 'reset'])
        ->middleware('throttle:5,1');

    // بدء الدخول الاجتماعي للموبايل — العودة عبر الـ callback الموحّد في مسارات الويب.
    Route::get('/auth/social/{provider}/redirect', [SocialAuthController::class, 'redirect'])
        ->middleware('throttle:30,1')
        ->where('provider', 'google|facebook|twitter|linkedin');

    Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function (): void {
        Route::get('/me', MeController::class);
        Route::post('/logout', [LogoutController::class, 'store']);

        // أجهزة الإشعارات (B5)
        Route::post('/devices', [DeviceTokenController::class, 'store']);
        Route::delete('/devices', [DeviceTokenController::class, 'destroy']);
        Route::get('/workspaces', [WorkspaceIndexController::class, 'index']);

        Route::middleware('api.super_admin')->prefix('admin')->group(function (): void {
            Route::get('/feature-flags', [AdminFeatureFlagController::class, 'index']);
            Route::patch('/feature-flags/{key}', [AdminFeatureFlagController::class, 'update']);
        });

        Route::middleware('api.workspace')->prefix('workspaces/{workspace_public_id}')->group(function (): void {
            Route::get('/tools', [ToolIndexController::class, 'index']);
            Route::get('/projects', [ProjectController::class, 'index']);
            Route::post('/projects', [ProjectController::class, 'store'])->middleware('idempotency:projects');

            Route::middleware('api.project')->prefix('projects/{project_public_id}')->group(function (): void {
                Route::get('/', [ProjectController::class, 'show']);
                Route::put('/', [ProjectController::class, 'update']);
                Route::delete('/', [ProjectController::class, 'destroy']);

                Route::get('/tools/{tcode}', [WorkspaceToolController::class, 'load']);
                Route::post('/tools/{tcode}/run', [WorkspaceToolController::class, 'run'])
                    ->middleware('idempotency:tool_run');

                // دورة المشروع الكاملة (B3)
                Route::get('/brief', [ProjectBriefController::class, 'show']);
                Route::put('/brief', [ProjectBriefController::class, 'update']);
                Route::post('/audit', [ProjectAuditController::class, 'store']);
                Route::get('/audit/status', [ProjectAuditController::class, 'status']);
                Route::get('/recommendations', [ProjectRecommendationController::class, 'index']);
                Route::post('/recommendations/{recommendationPublicId}/package', [ProjectRecommendationController::class, 'storePackage']);
                Route::get('/report', [ProjectReportController::class, 'report']);
                Route::get('/report/pdf', [ProjectReportController::class, 'reportPdf'])
                    ->middleware('entitlement:outputs.can_export');
                Route::get('/dossier', [ProjectReportController::class, 'dossier']);
                Route::get('/dossier/pdf', [ProjectReportController::class, 'dossierPdf'])
                    ->middleware('entitlement:outputs.can_export');

                Route::post('/approvals', [ApprovalController::class, 'store']);

                Route::get('/knowledge/uploads', [KnowledgeUploadController::class, 'index']);
                Route::post('/knowledge/uploads', [KnowledgeUploadController::class, 'store'])
                    ->middleware('throttle:10,1');
                Route::post('/knowledge/uploads/{uploadPublicId}/retry', [KnowledgeUploadController::class, 'retry'])
                    ->middleware('throttle:10,1');
                Route::delete('/knowledge/uploads/{uploadPublicId}', [KnowledgeUploadController::class, 'destroy']);
                Route::post('/knowledge/upload-sessions', [KnowledgeUploadSessionController::class, 'store'])->middleware('throttle:10,1');
                Route::put('/knowledge/upload-sessions/{sessionPublicId}/chunks/{index}', [KnowledgeUploadSessionController::class, 'chunk'])->whereNumber('index');
                Route::post('/knowledge/upload-sessions/{sessionPublicId}/complete', [KnowledgeUploadSessionController::class, 'complete'])->middleware('throttle:10,1');
            });

            // حزم التنفيذ
            Route::get('/execution-packages/{packagePublicId}', [ExecutionPackageController::class, 'show']);
            Route::patch('/execution-packages/{packagePublicId}', [ExecutionPackageController::class, 'update']);
            Route::patch('/execution-packages/{packagePublicId}/status', [ExecutionPackageController::class, 'updateStatus']);
            Route::post('/execution-packages/{packagePublicId}/reports', [ExecutionPackageController::class, 'storeReport']);
            Route::patch('/execution-tasks/{taskPublicId}', [ExecutionPackageController::class, 'updateTask']);
            Route::patch('/execution-tasks/{taskPublicId}/status', [ExecutionPackageController::class, 'updateTaskStatus']);

            // الاستوديو الذكي
            Route::get('/templates', [StudioTemplateController::class, 'index']);
            Route::get('/studio/generations', [StudioGenerationController::class, 'index']);
            Route::post('/studio/generations', [StudioGenerationController::class, 'store'])
                ->middleware('idempotency:studio');
            Route::get('/studio/generations/{generationPublicId}', [StudioGenerationController::class, 'show']);
            Route::get('/studio/generations/{generationPublicId}/export/{format}', [StudioGenerationController::class, 'export'])
                ->where('format', 'md|markdown|html|pdf')
                ->middleware('entitlement:outputs.can_export');
            Route::delete('/studio/generations/{generationPublicId}', [StudioGenerationController::class, 'destroy']);

            // مساعد الذكاء (chat/analyze/suggest/research)
            Route::middleware('throttle:ai-assist')->prefix('ai')->group(function (): void {
                Route::post('/chat', [AiAssistController::class, 'chat']);
                Route::post('/chat/stream', [AiAssistController::class, 'chatStream']);
                Route::get('/conversations', [AiConversationController::class, 'index']);
                Route::post('/conversations', [AiConversationController::class, 'store']);
                Route::get('/conversations/{conversationPublicId}', [AiConversationController::class, 'show']);
                Route::post('/conversations/{conversationPublicId}/messages', [AiConversationController::class, 'storeMessage']);
                Route::get('/conversations/{conversationPublicId}/messages/{messagePublicId}', [AiConversationController::class, 'showMessage']);
                Route::post('/analyze', [AiAssistController::class, 'analyze']);
                Route::post('/suggest', [AiAssistController::class, 'suggest']);
                Route::post('/research', [AiAssistController::class, 'research']);
            });

            // الداشبورد والإعداد الأولي (B4)
            Route::get('/dashboard', [WorkspaceDashboardController::class, 'show']);
            Route::get('/onboarding', [OnboardingController::class, 'show']);
            Route::post('/onboarding', [OnboardingController::class, 'store']);

            // الحساب
            Route::get('/account', [AccountController::class, 'show']);
            Route::patch('/account', [AccountController::class, 'update']);

            // مفتاح الذكاء الخاص بالحساب (BYOK) — المالك فقط
            Route::get('/account/ai-key', [AccountAiKeyController::class, 'show']);
            Route::put('/account/ai-key', [AccountAiKeyController::class, 'update']);
            Route::delete('/account/ai-key', [AccountAiKeyController::class, 'destroy']);

            // الفريق
            Route::get('/team', [TeamController::class, 'index']);
            Route::post('/team/invitations', [TeamController::class, 'invite']);
            Route::delete('/team/members/{memberId}', [TeamController::class, 'destroyMember'])
                ->whereNumber('memberId');
            Route::delete('/team/invitations/{invitationId}', [TeamController::class, 'destroyInvitation'])
                ->whereNumber('invitationId');

            // الموافقات
            Route::get('/approvals', [ApprovalController::class, 'index']);
            Route::patch('/approvals/{approvalId}', [ApprovalController::class, 'update'])
                ->whereNumber('approvalId');

            // عملاء الوكالة
            Route::get('/clients', [ClientController::class, 'index']);
            Route::post('/clients', [ClientController::class, 'store']);
            Route::put('/clients/{clientPublicId}', [ClientController::class, 'update']);
            Route::delete('/clients/{clientPublicId}', [ClientController::class, 'destroy']);

            // علامة الوكالة (white-label)
            Route::middleware('entitlement:white_label')->group(function (): void {
                Route::get('/agency/branding', [AgencyBrandingController::class, 'show']);
                Route::patch('/agency/branding', [AgencyBrandingController::class, 'update']);
            });

            // الفوترة (B5)
            Route::get('/billing', [BillingController::class, 'show']);
            Route::post('/billing/subscribe', [BillingController::class, 'subscribe']);
            Route::post('/billing/paypal/callback', [BillingController::class, 'callback']);
            Route::post('/billing/cancel', [BillingController::class, 'cancel']);
        });

        // قبول دعوة فريق (خارج نطاق مساحة محددة — الرمز يحدد المساحة)
        Route::post('/team/invitations/{token}/accept', [TeamController::class, 'accept']);
    });
});
