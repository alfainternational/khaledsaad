<?php

use App\Http\Controllers\Admin\AdminCreditPackController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminGatewayController;
use App\Http\Controllers\Admin\AdminPaymentController;
use App\Http\Controllers\Admin\AdminPlanController;
use App\Http\Controllers\Admin\AdminSettingsController;
use App\Http\Controllers\Admin\AdminToolController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\UsageController;
use App\Http\Controllers\App\AudienceLabController;
use App\Http\Controllers\App\BillingController;
use App\Http\Controllers\App\CheckoutController;
use App\Http\Controllers\App\CompetitorController;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\FeedbackController;
use App\Http\Controllers\App\GeoPackController;
use App\Http\Controllers\App\KpiController;
use App\Http\Controllers\App\NotificationController;
use App\Http\Controllers\App\ProjectController;
use App\Http\Controllers\App\PulseController;
use App\Http\Controllers\App\ReportController;
use App\Http\Controllers\App\ReportWatchController;
use App\Http\Controllers\App\TaskController;
use App\Http\Controllers\App\ToolCatalogController;
use App\Http\Controllers\App\ToolRunController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Site\GuestRunController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\LegalController;
use App\Http\Controllers\Site\ToolShowcaseController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

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

    Route::get('projects/{project}/tasks', [TaskController::class, 'index'])->name('projects.tasks');
    Route::patch('tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');

    Route::post('projects/{project}/kpis', [KpiController::class, 'store'])->name('kpis.store');
    Route::post('kpis/{kpi}/entries', [KpiController::class, 'record'])->name('kpis.record');

    Route::get('tools', [ToolCatalogController::class, 'index'])->name('tools.index');
    Route::get('tools/{tool}', [ToolCatalogController::class, 'show'])->name('tools.show');

    Route::post('projects/{project}/tools/{tool}/runs', [ToolRunController::class, 'start'])->name('runs.start');
    Route::get('runs/{run}/steps/{step}', [ToolRunController::class, 'step'])->name('runs.step');
    Route::post('runs/{run}/steps/{step}', [ToolRunController::class, 'saveStep'])->name('runs.step.save');
    Route::get('runs/{run}/review', [ToolRunController::class, 'review'])->name('runs.review');
    Route::post('runs/{run}/queue', [ToolRunController::class, 'queue'])->name('runs.queue');
    Route::get('runs/{run}/status', [ToolRunController::class, 'status'])->name('runs.status');
    Route::get('runs/{run}/progress', [ToolRunController::class, 'progress'])->name('runs.progress');
    Route::post('runs/{run}/retry', [ToolRunController::class, 'retry'])->name('runs.retry');

    Route::get('reports/{report}', [ReportController::class, 'show'])->name('reports.show');
    Route::post('reports/{report}/tasks', [ReportController::class, 'convert'])->name('reports.convert');

    // محرك النمو: التقرير الحي والتغذية الراجعة والنبض.
    Route::post('reports/{report}/watch', [ReportWatchController::class, 'store'])->name('reports.watch');
    Route::post('reports/{report}/unwatch', [ReportWatchController::class, 'destroy'])->name('reports.unwatch');
    Route::post('reports/{report}/feedback', [FeedbackController::class, 'store'])->name('reports.feedback');
    Route::get('pulse', [PulseController::class, 'index'])->name('pulse.index');

    // حزمة الظهور للآلات (GEO) — التوليد مقيد بمعدل لأنه يستدعي النموذج.
    Route::get('projects/{project}/geo', [GeoPackController::class, 'show'])->name('geo.show');
    Route::post('projects/{project}/geo', [GeoPackController::class, 'generate'])
        ->middleware('throttle:6,60')->name('geo.generate');
    Route::get('projects/{project}/geo/llms.txt', [GeoPackController::class, 'llms'])->name('geo.llms');

    // مختبر الجمهور الاصطناعي.
    Route::get('projects/{project}/audience-lab', [AudienceLabController::class, 'show'])->name('audience.show');
    Route::post('projects/{project}/audience-lab/panel', [AudienceLabController::class, 'buildPanel'])
        ->middleware('throttle:6,60')->name('audience.panel');
    Route::post('projects/{project}/audience-lab/tests', [AudienceLabController::class, 'test'])
        ->middleware('throttle:10,60')->name('audience.test');

    // إدارة المنافسين من التقرير: تأكيد مرشّح، استبعاده، أو إضافة محلي.
    Route::post('projects/{project}/competitors', [CompetitorController::class, 'store'])->name('competitors.store');
    Route::post('competitors/{competitor}/confirm', [CompetitorController::class, 'confirm'])->name('competitors.confirm');
    Route::post('competitors/{competitor}/dismiss', [CompetitorController::class, 'dismiss'])->name('competitors.dismiss');
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

// لوحة الإدارة محصورة بصلاحية admin عبر middleware مخصص.
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('usage', UsageController::class)->name('usage');
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
    Route::resource('plans', AdminPlanController::class)->except(['show']);
    Route::resource('packs', AdminCreditPackController::class)->except(['show']);
    Route::resource('gateways', AdminGatewayController::class)->except(['show']);
    Route::patch('gateways/{gateway}/toggle', [AdminGatewayController::class, 'toggle'])->name('gateways.toggle');

    // المستخدمون: عرض + تعديل + منح رصيد + صلاحية.
    Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}', [AdminUserController::class, 'update'])->name('users.update');
    Route::post('users/{user}/credits', [AdminUserController::class, 'grantCredits'])->name('users.credits');
    Route::patch('users/{user}/admin', [AdminUserController::class, 'toggleAdmin'])->name('users.admin');

    // سجل المدفوعات.
    Route::get('payments', [AdminPaymentController::class, 'index'])->name('payments.index');

    // المفاتيح والإعدادات (بريد، ذكاء، سوق) من اللوحة بدل .env، تسري فورًا.
    Route::get('settings', [AdminSettingsController::class, 'index'])->name('settings');
    Route::put('settings', [AdminSettingsController::class, 'update'])->name('settings.update');
});
