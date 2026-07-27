<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\Admin\BillingCatalogController as AdminBillingCatalogController;
use App\Http\Controllers\Api\V1\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\ManualReportController as AdminManualReportController;
use App\Http\Controllers\Api\V1\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Api\V1\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Api\V1\Admin\ToolController as AdminToolController;
use App\Http\Controllers\Api\V1\Admin\UsageController as AdminUsageController;
use App\Http\Controllers\Api\V1\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\V1\AgencyReportController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CompetitorController;
use App\Http\Controllers\Api\V1\ConsultationController;
use App\Http\Controllers\Api\V1\DeviceTokenController;
use App\Http\Controllers\Api\V1\EngagementController;
use App\Http\Controllers\Api\V1\GrowthController;
use App\Http\Controllers\Api\V1\GuestRunController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\PublicContentController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\RunController;
use App\Http\Controllers\Api\V1\SharedAgencyReportController as PublicSharedAgencyReportController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\ToolController;
use App\Support\Billing\FeatureKey;
use Illuminate\Support\Facades\Route;

/*
 * الإصدار الأول من الواجهة. تطبيق Flutter يستهلك هذه المسارات فقط،
 * وكل مسار منها يستدعي نفس الخدمة ونفس العارض اللذين يستدعيهما الويب.
 */
Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('auth/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:6,1')->name('auth.forgot-password');
    Route::post('auth/reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:6,1')->name('auth.reset-password');

    Route::prefix('public')->name('public.')->middleware('throttle:60,1')->group(function (): void {
        Route::get('bootstrap', [PublicContentController::class, 'bootstrap'])->name('bootstrap');
        Route::get('legal/{page}', [PublicContentController::class, 'legal'])->name('legal');
        Route::get('mobile-app', [PublicContentController::class, 'mobileApp'])->name('mobile-app');
        Route::post('tools/{tool}/runs', [GuestRunController::class, 'start'])->name('runs.start');
        Route::get('runs/{run}', [GuestRunController::class, 'show'])->name('runs.show');
        Route::put('runs/{run}/steps/{step}', [GuestRunController::class, 'saveStep'])->name('runs.step');
        Route::get('runs/{run}/preflight', [GuestRunController::class, 'preflight'])->name('runs.preflight');
        Route::get('shared-reports/{token}', [PublicSharedAgencyReportController::class, 'show'])->name('shared-reports.show');
        Route::get('shared-reports/{token}/pdf', [PublicSharedAgencyReportController::class, 'pdf'])->name('shared-reports.pdf');
    });

    Route::get('tools', [ToolController::class, 'index'])->name('tools.index');
    Route::get('tools/{tool}', [ToolController::class, 'show'])->name('tools.show');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('devices', [DeviceTokenController::class, 'store'])->name('devices.store');
        Route::delete('devices', [DeviceTokenController::class, 'destroy'])->name('devices.destroy');

        Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
        Route::put('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
        Route::post('projects/{project}/consultations', [ConsultationController::class, 'store'])->middleware('throttle:10,1')->name('consultations.store');
        Route::get('consultations/{consultation}', [ConsultationController::class, 'show'])->name('consultations.show');
        Route::put('consultations/{consultation}/answers/{question}', [ConsultationController::class, 'answer'])->middleware('throttle:120,1')->name('consultations.answer');
        Route::post('consultations/{consultation}/review', [ConsultationController::class, 'review'])->name('consultations.review');
        Route::post('consultations/{consultation}/confirm', [ConsultationController::class, 'confirm'])->middleware('throttle:6,1')->name('consultations.confirm');
        Route::post('consultations/{consultation}/retry', [ConsultationController::class, 'retry'])->name('consultations.retry');
        Route::get('consultations/{consultation}/status', [ConsultationController::class, 'status'])->name('consultations.status');
        Route::post('consultations/{consultation}/conflicts/{conflict}/resolve', [ConsultationController::class, 'resolveConflict'])->name('consultations.conflicts.resolve');
        Route::get('consultations/{consultation}/export', [ConsultationController::class, 'export'])->name('consultations.export');
        Route::delete('consultations/{consultation}', [ConsultationController::class, 'destroy'])->name('consultations.destroy');
        Route::post('consultations/{consultation}/evidence', [ConsultationController::class, 'uploadEvidence'])->middleware('throttle:20,1')->name('consultations.evidence.store');
        Route::delete('consultations/{consultation}/evidence/{evidence}', [ConsultationController::class, 'deleteEvidence'])->name('consultations.evidence.destroy');
        // نفس بوابات الميزات التي تحكم الويب حرفيًا: التطبيق ليس بابًا خلفيًا.
        Route::middleware('feature:'.FeatureKey::REPORTS_AGENCY)->group(function (): void {
            Route::get('projects/{project}/agency-reports', [AgencyReportController::class, 'index'])->name('projects.agency-reports.index');
            Route::post('projects/{project}/agency-reports', [AgencyReportController::class, 'store'])->name('projects.agency-reports.store');
            Route::post('projects/{project}/full-diagnosis', [AgencyReportController::class, 'sweep'])
                ->middleware('throttle:6,60')->name('projects.full-diagnosis');
            Route::get('agency-reports/{agencyReport}', [AgencyReportController::class, 'show'])->name('agency-reports.show');
            Route::get('agency-reports/{agencyReport}/pdf', [AgencyReportController::class, 'pdf'])->name('agency-reports.pdf');
            Route::get('agency-reports/{agencyReport}/data.json', [AgencyReportController::class, 'data'])->name('agency-reports.data');
            Route::post('agency-reports/{agencyReport}/share', [AgencyReportController::class, 'share'])->name('agency-reports.share');
            Route::delete('agency-reports/{agencyReport}/share', [AgencyReportController::class, 'revokeShare'])->name('agency-reports.share.revoke');
        });

        // نظير «أكمل ما بدأته» وحالة كل أداة داخل المشروع.
        Route::get('engagements/unfinished', [EngagementController::class, 'unfinished'])->name('engagements.unfinished');
        Route::get('projects/{project}/tools', [EngagementController::class, 'projectTools'])->name('engagements.project-tools');

        Route::get('projects/{project}/runs', [RunController::class, 'index'])->name('runs.index');
        Route::post('projects/{project}/tools/{tool}/runs', [RunController::class, 'store'])->name('runs.store');
        Route::get('runs/{run}', [RunController::class, 'show'])->name('runs.show');
        Route::put('runs/{run}/steps/{step}', [RunController::class, 'saveStep'])->name('runs.step');
        Route::post('runs/{run}/insights', [RunController::class, 'insights'])
            ->middleware('throttle:30,1')->name('runs.insights');
        Route::get('runs/{run}/preflight', [RunController::class, 'preflight'])->name('runs.preflight');
        Route::post('runs/{run}/files', [RunController::class, 'uploadFile'])->name('runs.files.store');
        Route::delete('runs/{run}/files/{file}', [RunController::class, 'deleteFile'])->name('runs.files.destroy');

        // إدارة المنافسين — نظير الويب.
        Route::get('projects/{project}/competitors', [CompetitorController::class, 'index'])->name('competitors.index');
        Route::post('projects/{project}/competitors', [CompetitorController::class, 'store'])->name('competitors.store');
        Route::post('competitors/{competitor}/confirm', [CompetitorController::class, 'confirm'])->name('competitors.confirm');
        Route::post('competitors/{competitor}/dismiss', [CompetitorController::class, 'dismiss'])->name('competitors.dismiss');
        Route::post('runs/{run}/queue', [RunController::class, 'queue'])->name('runs.queue');
        Route::post('runs/{run}/manual', [RunController::class, 'requestManualReview'])
            ->middleware('feature:'.FeatureKey::MANUAL_REVIEW)->name('runs.manual');
        Route::get('runs/{run}/progress', [RunController::class, 'progress'])->name('runs.progress');
        Route::post('runs/{run}/retry', [RunController::class, 'retry'])->name('runs.retry');

        Route::get('projects/{project}/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/{report}', [ReportController::class, 'show'])->name('reports.show');
        Route::post('reports/{report}/tasks', [ReportController::class, 'convert'])->name('reports.convert');
        // بوابة PDF داخل المتحكّم: الملكية قبل الاستحقاق.
        Route::get('reports/{report}/pdf', [AccountController::class, 'reportPdf'])->name('reports.pdf');

        // محرك النمو — نظير مسارات الويب واحدًا بواحد.
        Route::post('reports/{report}/watch', [GrowthController::class, 'watch'])->name('reports.watch');
        Route::post('reports/{report}/unwatch', [GrowthController::class, 'unwatch'])->name('reports.unwatch');
        Route::post('reports/{report}/feedback', [GrowthController::class, 'feedback'])->name('reports.feedback');
        Route::get('pulse', [GrowthController::class, 'pulse'])
            ->middleware('feature:'.FeatureKey::GROWTH_PULSE)->name('pulse.index');
        Route::middleware('feature:'.FeatureKey::GROWTH_GEO)->group(function (): void {
            Route::get('projects/{project}/geo', [GrowthController::class, 'geoShow'])->name('geo.show');
            Route::post('projects/{project}/geo', [GrowthController::class, 'geoGenerate'])
                ->middleware('throttle:6,60')->name('geo.generate');
            Route::get('projects/{project}/geo/llms.txt', [GrowthController::class, 'geoLlms'])->name('geo.llms');
        });
        Route::middleware('feature:'.FeatureKey::AUDIENCE_LAB)->group(function (): void {
            Route::get('projects/{project}/personas', [GrowthController::class, 'personas'])->name('personas.index');
            Route::post('projects/{project}/personas', [GrowthController::class, 'buildPanel'])
                ->middleware('throttle:6,60')->name('personas.build');
            Route::post('projects/{project}/personas/tests', [GrowthController::class, 'personaTest'])
                ->middleware('throttle:10,60')->name('personas.test');
        });

        // الأرصدة والإشعارات — نظير صفحات الويب.
        Route::get('billing', [AccountController::class, 'billing'])->name('billing');
        Route::get('billing/packs', [AccountController::class, 'creditPacks'])->name('billing.packs');
        Route::post('billing/subscribe/{plan}', [AccountController::class, 'subscribe'])->name('billing.subscribe');

        // الشراء عبر البوابة — نظير checkout في الويب.
        Route::post('checkout/plan/{plan}', [AccountController::class, 'checkoutPlan'])->name('checkout.plan');
        Route::post('checkout/pack/{pack}', [AccountController::class, 'checkoutPack'])->name('checkout.pack');
        Route::get('checkout/{payment}/callback', [AccountController::class, 'checkoutCallback'])->name('checkout.callback');
        Route::post('checkout/{payment}/cancel', [AccountController::class, 'checkoutCancel'])->name('checkout.cancel');
        Route::get('notifications', [AccountController::class, 'notifications'])->name('notifications.index');
        Route::post('notifications/{id}/read', [AccountController::class, 'markNotificationRead'])->name('notifications.read');
        Route::post('notifications/read-all', [AccountController::class, 'markAllNotificationsRead'])->name('notifications.read-all');

        Route::get('projects/{project}/tasks', [TaskController::class, 'index'])->name('tasks.index');
        Route::patch('tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
        Route::middleware('feature:'.FeatureKey::KPI_TRACKING)->group(function (): void {
            Route::post('projects/{project}/kpis', [TaskController::class, 'storeKpi'])->name('kpis.store');
            Route::post('kpis/{kpi}/entries', [TaskController::class, 'recordKpi'])->name('kpis.record');
        });

        Route::prefix('admin')->name('admin.')->middleware('admin')->group(function (): void {
            Route::get('dashboard', AdminDashboardController::class)->name('dashboard');
            Route::get('usage', AdminUsageController::class)->name('usage');
            Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
            Route::post('users/plans/preview', [AdminUserController::class, 'previewPlans'])->name('users.plans.preview');
            Route::post('users/plans/assign', [AdminUserController::class, 'assignPlans'])->name('users.plans.assign');
            Route::put('users/{user}', [AdminUserController::class, 'update'])->name('users.update');
            Route::post('users/{user}/credits', [AdminUserController::class, 'grantCredits'])->name('users.credits');
            Route::patch('users/{user}/admin', [AdminUserController::class, 'toggleAdmin'])->name('users.admin');
            Route::get('tools', [AdminToolController::class, 'index'])->name('tools.index');
            Route::post('tools', [AdminToolController::class, 'store'])->name('tools.store');
            Route::get('tools/{tool}', [AdminToolController::class, 'show'])->name('tools.show');
            Route::put('tools/{tool}', [AdminToolController::class, 'update'])->name('tools.update');
            Route::delete('tools/{tool}', [AdminToolController::class, 'destroy'])->name('tools.destroy');
            Route::patch('tools/{tool}/status', [AdminToolController::class, 'updateStatus'])->name('tools.status');
            Route::put('tools/{tool}/prompts/{prompt}', [AdminToolController::class, 'updatePrompt'])->name('tools.prompts.update');
            Route::get('catalog', AdminBillingCatalogController::class)->name('catalog');
            Route::post('features', [AdminBillingCatalogController::class, 'storeFeature'])->name('features.store');
            Route::put('features/{feature}', [AdminBillingCatalogController::class, 'updateFeature'])->name('features.update');
            Route::delete('features/{feature}', [AdminBillingCatalogController::class, 'destroyFeature'])->name('features.destroy');
            Route::post('plans', [AdminBillingCatalogController::class, 'storePlan'])->name('plans.store');
            Route::put('plans/{plan}', [AdminBillingCatalogController::class, 'updatePlan'])->name('plans.update');
            Route::delete('plans/{plan}', [AdminBillingCatalogController::class, 'destroyPlan'])->name('plans.destroy');
            Route::post('packs', [AdminBillingCatalogController::class, 'storePack'])->name('packs.store');
            Route::put('packs/{pack}', [AdminBillingCatalogController::class, 'updatePack'])->name('packs.update');
            Route::delete('packs/{pack}', [AdminBillingCatalogController::class, 'destroyPack'])->name('packs.destroy');
            Route::post('gateways', [AdminBillingCatalogController::class, 'storeGateway'])->name('gateways.store');
            Route::put('gateways/{gateway}', [AdminBillingCatalogController::class, 'updateGateway'])->name('gateways.update');
            Route::post('gateways/{gateway}/test', [AdminBillingCatalogController::class, 'testGateway'])->name('gateways.test');
            Route::patch('gateways/{gateway}/default', [AdminBillingCatalogController::class, 'defaultGateway'])->name('gateways.default');
            Route::patch('gateways/{gateway}/toggle', [AdminBillingCatalogController::class, 'toggleGateway'])->name('gateways.toggle');
            Route::delete('gateways/{gateway}', [AdminBillingCatalogController::class, 'destroyGateway'])->name('gateways.destroy');
            Route::get('payments', [AdminPaymentController::class, 'index'])->name('payments.index');
            Route::post('payments/{payment}/approve', [AdminPaymentController::class, 'approve'])->name('payments.approve');
            Route::post('payments/{payment}/reject', [AdminPaymentController::class, 'reject'])->name('payments.reject');
            Route::post('payments/{payment}/refund', [AdminPaymentController::class, 'refund'])->name('payments.refund');
            Route::get('manual-reports', [AdminManualReportController::class, 'index'])->name('manual-reports.index');
            Route::get('manual-reports/{run}', [AdminManualReportController::class, 'show'])->name('manual-reports.show');
            Route::post('manual-reports/{run}', [AdminManualReportController::class, 'store'])->name('manual-reports.store');
            Route::get('settings', [AdminSettingController::class, 'index'])->name('settings.index');
            Route::put('settings', [AdminSettingController::class, 'update'])->name('settings.update');
        });
    });
});
