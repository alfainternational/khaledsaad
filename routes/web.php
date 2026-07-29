<?php

use App\Http\Controllers\Admin\AdminConsultationController;
use App\Http\Controllers\Admin\AdminCreditPackController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminFeatureController;
use App\Http\Controllers\Admin\AdminGatewayController;
use App\Http\Controllers\Admin\AdminManualReportController;
use App\Http\Controllers\Admin\AdminPaymentController;
use App\Http\Controllers\Admin\AdminPlanController;
use App\Http\Controllers\Admin\AdminSettingsController;
use App\Http\Controllers\Admin\AdminToolController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\UsageController;
use App\Http\Controllers\App\AgencyReportController;
use App\Http\Controllers\App\AudienceLabController;
use App\Http\Controllers\App\BillingController;
use App\Http\Controllers\App\CheckoutController;
use App\Http\Controllers\App\CompetitorController;
use App\Http\Controllers\App\ConsultationController;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\FeedbackController;
use App\Http\Controllers\App\GeoPackController;
use App\Http\Controllers\App\KpiController;
use App\Http\Controllers\App\NotificationController;
use App\Http\Controllers\App\PortfolioController;
use App\Http\Controllers\App\PresenceController;
use App\Http\Controllers\App\ProjectController;
use App\Http\Controllers\App\PulseController;
use App\Http\Controllers\App\ReadinessController;
use App\Http\Controllers\App\ReportController;
use App\Http\Controllers\App\ReportWatchController;
use App\Http\Controllers\App\TaskController;
use App\Http\Controllers\App\ToolCatalogController;
use App\Http\Controllers\App\ToolRunController;
use App\Http\Controllers\App\VoiceIntakeController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Site\GuestRunController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\LegalController;
use App\Http\Controllers\Site\MobileAppController;
use App\Http\Controllers\Site\SharedAgencyReportController;
use App\Http\Controllers\Site\ToolShowcaseController;
use App\Http\Controllers\Webhooks\MoyasarWebhookController;
use App\Http\Controllers\Webhooks\PayPalWebhookController;
use App\Http\Controllers\Webhooks\TapWebhookController;
use App\Support\Billing\FeatureKey;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('download/android', MobileAppController::class)->name('mobile.download');

// واجهة الأدوات العامة: يراها الزائر قبل التسجيل ليعرف ما الذي سيدخل إليه.
Route::get('tools', [ToolShowcaseController::class, 'index'])->name('tools.index');
Route::get('tools/{tool}', [ToolShowcaseController::class, 'show'])->name('tools.show');

// التجربة بلا حساب: يجرّب أولًا ثم يقرر التسجيل.
Route::middleware('throttle:12,60')->group(function (): void {
    Route::post('try/{tool}', [GuestRunController::class, 'start'])->name('try.start');
});
Route::get('try/{run}/steps/{step}', [GuestRunController::class, 'step'])->name('try.step');
Route::post('try/{run}/steps/{step}', [GuestRunController::class, 'saveStep'])->name('try.step.save');
Route::get('try/{run}/result', [GuestRunController::class, 'result'])->name('try.result');

/*
 * موجز الوكالة عبر رابط المشاركة: عام بلا تسجيل دخول، لكنه محدود بالمعدل
 * كي لا يُستخدم لتخمين الرموز، وبلا فهرسة في محركات البحث.
 */
Route::middleware('throttle:30,1')->group(function (): void {
    Route::get('r/{token}', [SharedAgencyReportController::class, 'show'])->name('shared.agency-report');
    Route::get('r/{token}/pdf', [SharedAgencyReportController::class, 'pdf'])->name('shared.agency-report.pdf');
    Route::get('r/{token}/data.json', [SharedAgencyReportController::class, 'data'])->name('shared.agency-report.data');
});

// الصفحات القانونية بلغة مفهومة، لا روابط تعيدك إلى الأسئلة الشائعة.
Route::get('privacy', LegalController::class)->defaults('page', 'privacy')->name('privacy');
Route::get('terms', LegalController::class)->defaults('page', 'terms')->name('terms');

Route::middleware('guest')->group(function (): void {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    // من نسي كلمة مروره كان يخرج من المنتج نهائيًا قبل هذا المسار.
    Route::get('forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'email'])->name('password.email');
    Route::get('reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'update'])->name('password.update');
});

Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')->name('logout');

Route::middleware('auth')->prefix('app')->name('app.')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::get('projects/{project}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
    Route::put('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::post('projects/{project}/consultations', [ConsultationController::class, 'start'])->name('consultations.start');

    /*
     * بطاقة الجاهزية: المحور السابع. الشاشة والفحص مفتوحان — هما الإسفين الذي
     * يثبت القيمة قبل أن يُطلب اشتراك، وهما حدّ المستوى ٠: يعرف صاحب النشاط
     * **أين** فجواته.
     *
     * أما التصدير فخلف `diagnosis.full`: المستند هو ما يُشارَك ويُبنى عليه
     * عمل، وهو حدّ المستوى ١ (§٨).
     */
    /*
     * الاستقبال الصوتي: يتكلّم صاحب النشاط بدل أن يكتب. يعيد نصًّا للمراجعة
     * ولا يحفظ حقيقة — خطأ نسخ في الدماغ أسوأ من فجوة معلنة.
     */
    Route::post('projects/{project}/voice', [VoiceIntakeController::class, 'store'])
        ->middleware('throttle:20,60')->name('voice.store');

    Route::get('projects/{project}/readiness', [ReadinessController::class, 'show'])->name('readiness.show');
    Route::post('projects/{project}/readiness/audit', [ReadinessController::class, 'audit'])
        ->middleware('throttle:10,60')->name('readiness.audit');
    Route::post('projects/{project}/readiness/log', [ReadinessController::class, 'uploadLog'])
        ->middleware('throttle:20,60')->name('readiness.log');
    Route::get('projects/{project}/readiness/pdf', [ReadinessController::class, 'download'])
        ->middleware('feature:'.FeatureKey::DIAGNOSIS_FULL)->name('readiness.download');

    /*
     * تقرير الحضور في إجابات النماذج (المرحلة ٣): أول قدرة بتكلفة متغيرة،
     * ولذلك خلف `diagnosis.full` — والسقف التشغيلي يحرسها فوق ذلك.
     */
    Route::middleware('feature:'.FeatureKey::DIAGNOSIS_FULL)->group(function (): void {
        Route::get('projects/{project}/presence', [PresenceController::class, 'show'])->name('presence.show');
        Route::post('projects/{project}/presence/probe', [PresenceController::class, 'probe'])
            ->middleware('throttle:5,60')->name('presence.probe');
    });
    Route::get('consultations', [ConsultationController::class, 'index'])->name('consultations.index');
    Route::get('consultations/{consultation}', [ConsultationController::class, 'show'])->name('consultations.show');
    Route::post('consultations/{consultation}/answer', [ConsultationController::class, 'answer'])->name('consultations.answer');
    Route::put('consultations/{consultation}/answers/{question}', [ConsultationController::class, 'revise'])->name('consultations.answers.update');
    Route::post('consultations/{consultation}/review', [ConsultationController::class, 'review'])->name('consultations.review');
    Route::post('consultations/{consultation}/confirm', [ConsultationController::class, 'confirm'])->name('consultations.confirm');
    Route::post('consultations/{consultation}/retry', [ConsultationController::class, 'retry'])->name('consultations.retry');
    Route::post('consultations/{consultation}/conflicts/{conflict}/resolve', [ConsultationController::class, 'resolveConflict'])->name('consultations.conflicts.resolve');
    Route::get('consultations/{consultation}/export', [ConsultationController::class, 'export'])->name('consultations.export');
    Route::delete('consultations/{consultation}', [ConsultationController::class, 'destroy'])->name('consultations.destroy');
    Route::post('consultations/{consultation}/evidence', [ConsultationController::class, 'uploadEvidence'])->middleware('throttle:20,1')->name('consultations.evidence.store');
    Route::delete('consultations/{consultation}/evidence/{evidence}', [ConsultationController::class, 'deleteEvidence'])->name('consultations.evidence.destroy');
    // تقرير الوكالة عنصر ميزة: البوابة على المسار نفسه، لا في الواجهة فقط.
    /*
     * لوحة الوكالة: محفظة الأنشطة كلها في شاشة واحدة. نطاقها مساحة العمل لا
     * مشروعًا، لأن المساحة هي حاوية الملكية (§٥.٢).
     */
    Route::middleware('feature:'.FeatureKey::REPORTS_AGENCY)->group(function (): void {
        Route::get('portfolio', PortfolioController::class)->name('portfolio');
    });

    Route::middleware('feature:'.FeatureKey::REPORTS_AGENCY)->group(function (): void {
        Route::get('projects/{project}/agency-reports', [AgencyReportController::class, 'index'])->name('projects.agency-reports.index');
        Route::post('projects/{project}/agency-reports', [AgencyReportController::class, 'store'])->name('projects.agency-reports.store');
        // التشخيص الشامل: أمر واحد يشغّل الأدوات كلها ثم يبني المستند.
        Route::post('projects/{project}/full-diagnosis', [AgencyReportController::class, 'sweep'])
            ->middleware('throttle:6,60')->name('projects.full-diagnosis');
        Route::post('projects/{project}/agency-brief', [AgencyReportController::class, 'saveBrief'])->name('projects.agency-reports.brief');
        Route::get('agency-reports/{agencyReport}', [AgencyReportController::class, 'show'])->name('agency-reports.show');
        Route::get('agency-reports/{agencyReport}/brief', [AgencyReportController::class, 'brief'])->name('agency-reports.brief');
        Route::get('agency-reports/{agencyReport}/brief/pdf', [AgencyReportController::class, 'briefPdf'])->name('agency-reports.brief.pdf');
        Route::get('agency-reports/{agencyReport}/pdf', [AgencyReportController::class, 'pdf'])->name('agency-reports.pdf');
        Route::get('agency-reports/{agencyReport}/data.json', [AgencyReportController::class, 'data'])->name('agency-reports.data');
        Route::post('agency-reports/{agencyReport}/share', [AgencyReportController::class, 'share'])->name('agency-reports.share');
        Route::delete('agency-reports/{agencyReport}/share', [AgencyReportController::class, 'revokeShare'])->name('agency-reports.share.revoke');
    });

    Route::get('projects/{project}/tasks', [TaskController::class, 'index'])->name('projects.tasks');
    Route::patch('tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');

    Route::middleware('feature:'.FeatureKey::KPI_TRACKING)->group(function (): void {
        Route::post('projects/{project}/kpis', [KpiController::class, 'store'])->name('kpis.store');
        Route::post('kpis/{kpi}/entries', [KpiController::class, 'record'])->name('kpis.record');
    });

    Route::get('tools', [ToolCatalogController::class, 'index'])->name('tools.index');
    Route::get('tools/{tool}', [ToolCatalogController::class, 'show'])->name('tools.show');

    Route::post('projects/{project}/tools/{tool}/runs', [ToolRunController::class, 'start'])->name('runs.start');
    Route::get('runs/{run}/steps/{step}', [ToolRunController::class, 'step'])->name('runs.step');
    Route::post('runs/{run}/steps/{step}', [ToolRunController::class, 'saveStep'])->name('runs.step.save');
    Route::post('runs/{run}/insights', [ToolRunController::class, 'insights'])
        ->middleware('throttle:30,1')->name('runs.insights');
    Route::get('runs/{run}/review', [ToolRunController::class, 'review'])->name('runs.review');
    Route::post('runs/{run}/queue', [ToolRunController::class, 'queue'])->name('runs.queue');
    // المسار اليدوي: مراجعة بشرية بدل خط الأنابيب.
    Route::post('runs/{run}/manual', [ToolRunController::class, 'requestManualReview'])
        ->middleware('feature:'.FeatureKey::MANUAL_REVIEW)->name('runs.manual');
    Route::get('runs/{run}/status', [ToolRunController::class, 'status'])->name('runs.status');
    Route::get('runs/{run}/progress', [ToolRunController::class, 'progress'])->name('runs.progress');
    Route::post('runs/{run}/retry', [ToolRunController::class, 'retry'])->name('runs.retry');

    Route::get('reports/{report}', [ReportController::class, 'show'])->name('reports.show');
    Route::post('reports/{report}/tasks', [ReportController::class, 'convert'])->name('reports.convert');

    // محرك النمو: التقرير الحي والتغذية الراجعة والنبض.
    // المتابعة محكومة بعدد (watchers) فتُفحص داخل المتحكّم لا هنا.
    Route::post('reports/{report}/watch', [ReportWatchController::class, 'store'])->name('reports.watch');
    Route::post('reports/{report}/unwatch', [ReportWatchController::class, 'destroy'])->name('reports.unwatch');
    Route::post('reports/{report}/feedback', [FeedbackController::class, 'store'])->name('reports.feedback');
    Route::get('pulse', [PulseController::class, 'index'])
        ->middleware('feature:'.FeatureKey::GROWTH_PULSE)->name('pulse.index');

    // حزمة الظهور للآلات (GEO) — التوليد مقيد بمعدل لأنه يستدعي النموذج.
    Route::middleware('feature:'.FeatureKey::GROWTH_GEO)->group(function (): void {
        Route::get('projects/{project}/geo', [GeoPackController::class, 'show'])->name('geo.show');
        Route::post('projects/{project}/geo', [GeoPackController::class, 'generate'])
            ->middleware('throttle:6,60')->name('geo.generate');
        Route::get('projects/{project}/geo/llms.txt', [GeoPackController::class, 'llms'])->name('geo.llms');
    });

    // مختبر الجمهور الاصطناعي.
    Route::middleware('feature:'.FeatureKey::AUDIENCE_LAB)->group(function (): void {
        Route::get('projects/{project}/audience-lab', [AudienceLabController::class, 'show'])->name('audience.show');
        Route::post('projects/{project}/audience-lab/panel', [AudienceLabController::class, 'buildPanel'])
            ->middleware('throttle:6,60')->name('audience.panel');
        Route::post('projects/{project}/audience-lab/tests', [AudienceLabController::class, 'test'])
            ->middleware('throttle:10,60')->name('audience.test');
    });

    // إدارة المنافسين من التقرير: تأكيد مرشّح، استبعاده، أو إضافة محلي.
    Route::post('projects/{project}/competitors', [CompetitorController::class, 'store'])->name('competitors.store');
    Route::post('competitors/{competitor}/confirm', [CompetitorController::class, 'confirm'])->name('competitors.confirm');
    Route::post('competitors/{competitor}/dismiss', [CompetitorController::class, 'dismiss'])->name('competitors.dismiss');
    // بوابة PDF داخل المتحكّم لا هنا: الملكية قبل الاستحقاق (404 قبل الترقية).
    Route::get('reports/{report}/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');

    // رفع أدلة أثناء تعبئة الأداة.
    Route::post('runs/{run}/files', [ToolRunController::class, 'uploadFile'])->name('runs.files.store');
    Route::delete('runs/{run}/files/{file}', [ToolRunController::class, 'deleteFile'])->name('runs.files.destroy');

    // الإشعارات: الجرس داخل المنصة.
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    // الفوترة والأرصدة.
    Route::get('billing', [BillingController::class, 'index'])->name('billing');
    Route::post('billing/subscribe/{plan}', [BillingController::class, 'subscribe'])->name('billing.subscribe');

    // الشراء عبر بوابة الدفع المفعّلة.
    Route::post('checkout/pack/{pack}', [CheckoutController::class, 'creditPack'])->name('checkout.pack');
    Route::post('checkout/plan/{plan}', [CheckoutController::class, 'plan'])->name('checkout.plan');
    Route::get('checkout/{payment}/callback', [CheckoutController::class, 'callback'])->name('checkout.callback');
    Route::get('checkout/{payment}/cancel', [CheckoutController::class, 'cancel'])->name('checkout.cancel');
});

// إشعار PayPal: هو ما يضمن وصول الرصيد حتى لو أغلق العميل المتصفح بعد الدفع.
// لا جلسة ولا CSRF — التحقق بتوقيع PayPal داخل المتحكّم.
Route::post('webhooks/paypal', PayPalWebhookController::class)->name('webhooks.paypal');
Route::post('webhooks/moyasar', MoyasarWebhookController::class)->name('webhooks.moyasar');
Route::post('webhooks/tap', TapWebhookController::class)->name('webhooks.tap');

// لوحة الإدارة محصورة بصلاحية admin عبر middleware مخصص.
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('usage', UsageController::class)->name('usage');
    Route::get('consultations', [AdminConsultationController::class, 'index'])->name('consultations.index');
    Route::get('consultations/versions/{version}', [AdminConsultationController::class, 'show'])->name('consultations.show');
    Route::post('consultations/{blueprint}/drafts', [AdminConsultationController::class, 'createDraft'])->name('consultations.drafts.store');
    Route::put('consultations/versions/{version}/questions/{question}', [AdminConsultationController::class, 'updateQuestion'])->name('consultations.questions.update');
    Route::post('consultations/versions/{version}/publish', [AdminConsultationController::class, 'publish'])->name('consultations.publish');
    Route::get('consultations/versions/{version}/simulate', [AdminConsultationController::class, 'simulate'])->name('consultations.simulate');
    // الأدوات: CRUD كامل + إدارة الحقول والبرومبتات من الواجهة.
    Route::get('tools', [AdminToolController::class, 'index'])->name('tools.index');
    Route::get('tools/create', [AdminToolController::class, 'create'])->name('tools.create');
    Route::post('tools', [AdminToolController::class, 'store'])->name('tools.store');
    Route::get('tools/{tool}', [AdminToolController::class, 'show'])->name('tools.show');
    Route::get('tools/{tool}/edit', [AdminToolController::class, 'edit'])->name('tools.edit');
    Route::put('tools/{tool}', [AdminToolController::class, 'update'])->name('tools.update');
    Route::delete('tools/{tool}', [AdminToolController::class, 'destroy'])->name('tools.destroy');
    Route::patch('tools/{tool}/status', [AdminToolController::class, 'updateStatus'])->name('tools.status');
    Route::put('tools/{tool}/prompts/{prompt}', [AdminToolController::class, 'updatePrompt'])->name('tools.prompts.update');

    // الخطط وحزم الأرصدة وبوابات الدفع: CRUD كامل.
    // فهرس الميزات: عناصر حقيقية تُختار في الخطط، لا سطور نصّية.
    Route::resource('features', AdminFeatureController::class)->except(['show']);
    Route::resource('plans', AdminPlanController::class)->except(['show']);
    Route::resource('packs', AdminCreditPackController::class)->except(['show']);
    Route::resource('gateways', AdminGatewayController::class)->except(['show']);
    Route::post('gateways/{gateway}/test', [AdminGatewayController::class, 'test'])->name('gateways.test');
    Route::patch('gateways/{gateway}/default', [AdminGatewayController::class, 'setDefault'])->name('gateways.default');
    Route::patch('gateways/{gateway}/toggle', [AdminGatewayController::class, 'toggle'])->name('gateways.toggle');

    // المستخدمون: عرض + تعديل + منح رصيد + صلاحية.
    Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('users/plans/bulk', [AdminUserController::class, 'bulkPlans'])->name('users.plans.bulk');
    Route::post('users/plans/preview', [AdminUserController::class, 'previewPlans'])->name('users.plans.preview');
    Route::post('users/plans/assign', [AdminUserController::class, 'assignPlans'])->name('users.plans.assign');
    Route::get('users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}', [AdminUserController::class, 'update'])->name('users.update');
    Route::post('users/{user}/plan', [AdminUserController::class, 'assignPlan'])->name('users.plan.assign');
    Route::post('users/{user}/credits', [AdminUserController::class, 'grantCredits'])->name('users.credits');
    Route::patch('users/{user}/admin', [AdminUserController::class, 'toggleAdmin'])->name('users.admin');

    // سجل المدفوعات + اعتماد التحويلات اليدوية.
    Route::get('payments', [AdminPaymentController::class, 'index'])->name('payments.index');
    Route::post('payments/{payment}/approve', [AdminPaymentController::class, 'approve'])->name('payments.approve');
    Route::post('payments/{payment}/reject', [AdminPaymentController::class, 'reject'])->name('payments.reject');
    Route::post('payments/{payment}/refund', [AdminPaymentController::class, 'refund'])->name('payments.refund');

    // المفاتيح والإعدادات (بريد، ذكاء، سوق) من اللوحة بدل .env، تسري فورًا.
    // طابور المراجعة اليدوية للتقارير.
    Route::get('manual', [AdminManualReportController::class, 'index'])->name('manual.index');
    Route::get('manual/{run}', [AdminManualReportController::class, 'show'])->name('manual.show');
    Route::get('manual/{run}/export', [AdminManualReportController::class, 'export'])->name('manual.export');
    Route::post('manual/{run}', [AdminManualReportController::class, 'store'])->name('manual.store');

    Route::get('settings', [AdminSettingsController::class, 'index'])->name('settings');
    Route::put('settings', [AdminSettingsController::class, 'update'])->name('settings.update');
});
