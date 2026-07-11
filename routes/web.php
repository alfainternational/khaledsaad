<?php

use App\Http\Controllers\Admin\AccountManagementController;
use App\Http\Controllers\Admin\AICreditsController;
use App\Http\Controllers\Admin\AiControlController;
use App\Http\Controllers\Admin\AiLabController;
use App\Http\Controllers\Admin\AIGenerationController;
use App\Http\Controllers\Admin\AITemplateController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\BlogPostController;
use App\Http\Controllers\Admin\CaseStudyController;
use App\Http\Controllers\Admin\ClientManagementController;
use App\Http\Controllers\Admin\CmsPageController;
use App\Http\Controllers\Admin\CommentModerationController;
use App\Http\Controllers\Admin\CommunityPostController;
use App\Http\Controllers\Admin\ContactInboxController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FeatureFlagController;
use App\Http\Controllers\Admin\MarketingTemplateHighlightController;
use App\Http\Controllers\Admin\PartnerController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\ProjectManagementController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\ToolController;
use App\Http\Controllers\Admin\ToolRunController as AdminToolRunController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\WorkspaceEntitlementController;
use App\Http\Controllers\Admin\WorkspaceManagementController;
use App\Http\Controllers\Api\AiChatController;
use App\Http\Controllers\Api\ToolRunApiController;
use App\Http\Controllers\Web\AccountController;
use App\Http\Controllers\Web\ApprovalController;
use App\Http\Controllers\PayPalWebhookController;
use App\Http\Controllers\Web\AgencyBrandingController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\BillingController;
use App\Http\Controllers\Web\ClientController;
use App\Http\Controllers\Web\ContactFormController;
use App\Http\Controllers\Web\DashboardController as UserDashboardController;
use App\Http\Controllers\Web\ExecutionPackageController;
use App\Http\Controllers\Web\ExperienceController;
use App\Http\Controllers\Web\GuestDiagnosisController;
use App\Http\Controllers\Web\ProjectDossierController;
use App\Http\Controllers\Web\ProjectReportController;
use App\Http\Controllers\Web\RecommendationController;
use App\Http\Controllers\Web\MarketingWebsiteController;
use App\Http\Controllers\Web\ImpersonationController;
use App\Http\Controllers\Web\OnboardingController;
use App\Http\Controllers\Web\PlatformController;
use App\Http\Controllers\Web\ProjectController;
use App\Http\Controllers\Web\ProjectMarketingBriefController;
use App\Http\Controllers\Web\StudioGenerationController;
use App\Http\Controllers\Web\TeamController;
use App\Http\Controllers\Web\ToolController as WebToolController;
use App\Http\Controllers\Web\ToolRunController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->prefix('admin')->group(function (): void {
    Route::get('/login', [AdminAuthController::class, 'create'])->name('admin.login');
    Route::post('/login', [AdminAuthController::class, 'store'])->name('admin.login.store');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::post('/logout', [AdminAuthController::class, 'destroy'])->name('logout');
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::resource('users', UserManagementController::class);
    Route::patch('/users/{user}/status', [UserManagementController::class, 'updateStatus'])->name('users.status');
    Route::post('/users/{user}/reset-password', [UserManagementController::class, 'resetPassword'])->name('users.reset-password');
    Route::post('/users/{user}/impersonate', [UserManagementController::class, 'impersonate'])->name('users.impersonate');

    Route::resource('accounts', AccountManagementController::class);
    Route::patch('/accounts/{account}/status', [AccountManagementController::class, 'updateStatus'])->name('accounts.status');
    Route::patch('/accounts/{account}/subscription', [AccountManagementController::class, 'updateSubscription'])->name('accounts.subscription');
    Route::resource('workspaces', WorkspaceManagementController::class);
    Route::patch('/workspaces/{workspace}/status', [WorkspaceManagementController::class, 'updateStatus'])->name('workspaces.status');

    Route::resource('plans', PlanController::class)->except(['show']);
    Route::resource('tools', ToolController::class)->except(['show']);
    Route::resource('ai-templates', AITemplateController::class)->except(['show'])->parameters([
        'ai-templates' => 'aiTemplate',
    ]);
    Route::resource('feature-flags', FeatureFlagController::class)->except(['show'])->parameters([
        'feature-flags' => 'featureFlag',
    ]);
    Route::get('/subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
    Route::get('/subscriptions/{subscription}', [SubscriptionController::class, 'show'])->name('subscriptions.show');
    Route::patch('/subscriptions/{subscription}', [SubscriptionController::class, 'update'])->name('subscriptions.update');
    Route::post('/subscriptions/{subscription}/extend', [SubscriptionController::class, 'extend'])->name('subscriptions.extend');

    Route::get('/workspaces/{workspace}/entitlements', [WorkspaceEntitlementController::class, 'index'])->name('workspaces.entitlements.index');
    Route::post('/workspaces/{workspace}/entitlements', [WorkspaceEntitlementController::class, 'store'])->name('workspaces.entitlements.store');
    Route::delete('/workspaces/{workspace}/entitlements/{entitlement}', [WorkspaceEntitlementController::class, 'destroy'])->name('workspaces.entitlements.destroy');

    Route::resource('projects', ProjectManagementController::class)->except(['create', 'store']);
    Route::resource('clients', ClientManagementController::class)->except(['create', 'store']);

    Route::get('/tool-runs', [AdminToolRunController::class, 'index'])->name('tool-runs.index');
    Route::get('/tool-runs/{toolRun}', [AdminToolRunController::class, 'show'])->name('tool-runs.show');
    Route::patch('/tool-runs/{toolRun}/ops', [AdminToolRunController::class, 'updateOps'])->name('tool-runs.ops');
    Route::post('/tool-runs/{toolRun}/retry', [AdminToolRunController::class, 'retry'])->name('tool-runs.retry');

    Route::get('/ai-generations', [AIGenerationController::class, 'index'])->name('ai-generations.index');
    Route::get('/ai-generations/{aiGeneration}', [AIGenerationController::class, 'show'])->name('ai-generations.show');
    Route::patch('/ai-generations/{aiGeneration}/ops', [AIGenerationController::class, 'updateOps'])->name('ai-generations.ops');
    Route::post('/ai-generations/{aiGeneration}/retry', [AIGenerationController::class, 'retry'])->name('ai-generations.retry');

    Route::get('/ai-credits', [AICreditsController::class, 'index'])->name('ai-credits.index');
    Route::post('/ai-credits', [AICreditsController::class, 'store'])->name('ai-credits.store');

    Route::get('/ai-control', [AiControlController::class, 'index'])->name('ai-control.index');
    Route::patch('/ai-control', [AiControlController::class, 'update'])->name('ai-control.update');
    Route::patch('/ai-control/providers', [AiControlController::class, 'updateProviders'])->name('ai-control.providers');
    Route::post('/ai-control/learn', [AiControlController::class, 'learn'])->name('ai-control.learn');
    Route::delete('/ai-control/knowledge', [AiControlController::class, 'forgetKnowledge'])->name('ai-control.knowledge.forget');

    Route::get('/ai-lab', [AiLabController::class, 'index'])->name('ai-lab.index');
    Route::post('/ai-lab/run', [AiLabController::class, 'run'])->name('ai-lab.run');
    Route::post('/ai-lab/command', [AiLabController::class, 'command'])->name('ai-lab.command');
    Route::post('/ai-lab/judge', [AiLabController::class, 'judge'])->name('ai-lab.judge');

    // كتالوج قدرات الوكلاء الـ25 (سطح «الكشف الانتقائي» — للقراءة).
    Route::get('/agents', [\App\Http\Controllers\Admin\AgentCatalogController::class, 'index'])->name('agents.index');

    Route::get('/comments', [CommentModerationController::class, 'index'])->name('comments.index');
    Route::delete('/comments/{comment}', [CommentModerationController::class, 'destroy'])->name('comments.destroy');
    Route::post('/comments/bulk-destroy', [CommentModerationController::class, 'bulkDestroy'])->name('comments.bulk-destroy');

    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('/audit-logs/export', [AuditLogController::class, 'export'])->name('audit-logs.export');

    Route::resource('cms-pages', CmsPageController::class)->except(['show'])->parameters(['cms-pages' => 'cmsPage']);
    Route::resource('blog-posts', BlogPostController::class)->except(['show'])->parameters(['blog-posts' => 'blogPost']);
    Route::resource('case-studies', CaseStudyController::class)->except(['show'])->parameters(['case-studies' => 'caseStudy']);
    Route::resource('community-posts', CommunityPostController::class)->except(['show'])->parameters(['community-posts' => 'communityPost']);
    Route::resource('marketing-template-highlights', MarketingTemplateHighlightController::class)->except(['show'])->parameters(['marketing-template-highlights' => 'marketingTemplateHighlight']);
    Route::resource('partners', PartnerController::class)->except(['show']);
    Route::resource('contact-messages', ContactInboxController::class)->only(['index', 'show', 'update', 'destroy']);
    Route::post('/contact-messages/{contact_message}/convert', [ContactInboxController::class, 'convert'])->name('contact-messages.convert');
});

Route::post('/paypal/webhook', [PayPalWebhookController::class, 'handle'])->name('paypal.webhook');

// جسر عودة الدفع للموبايل: PayPal يعيد المتصفح هنا، ونقفز فوراً إلى deep link التطبيق.
// عام بلا مصادقة — لا يمرّر سوى معاملات الاستعلام كما هي، والتحقق الفعلي يتم في API callback.
Route::get('/billing/mobile/return', function () {
    return redirect()->away('ksgrowth://billing/return?'.http_build_query(request()->query()));
})->name('billing.mobile.return');
Route::get('/billing/mobile/cancelled', function () {
    return redirect()->away('ksgrowth://billing/cancelled');
})->name('billing.mobile.cancelled');

// Public pre-registration diagnosis funnel (Phase أ) — open to guests and logged-in users.
Route::prefix('diagnose')->name('diagnose.')->group(function (): void {
    Route::get('/', [GuestDiagnosisController::class, 'form'])->name('form');
    Route::post('/', [GuestDiagnosisController::class, 'start'])
        ->middleware('throttle:6,1')
        ->name('start');
    Route::get('/{case}', [GuestDiagnosisController::class, 'show'])->name('show');
    Route::get('/{case}/status', [GuestDiagnosisController::class, 'status'])->name('status');
    Route::post('/{case}/email', [GuestDiagnosisController::class, 'captureEmail'])->name('email');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/impersonation/stop', [ImpersonationController::class, 'stop'])->name('impersonation.stop');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', UserDashboardController::class)->name('dashboard');
    Route::post('/dashboard/workspaces/{workspace}/switch', [UserDashboardController::class, 'switchWorkspace'])->name('dashboard.workspaces.switch');
    Route::get('/onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('/onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');
    // مسارات توافق مع CLAUDE.md — التدفق الحالي موحّد في شاشة واحدة
    Route::redirect('/onboarding/context', '/onboarding', 302)->name('onboarding.context');
    Route::redirect('/onboarding/who-are-you', '/onboarding', 302)->name('onboarding.who-are-you');
    Route::redirect('/onboarding/your-goal', '/onboarding', 302)->name('onboarding.your-goal');
    Route::redirect('/onboarding/suggested-path', '/onboarding', 302)->name('onboarding.suggested-path');
    Route::get('/clients/create', [ClientController::class, 'create'])->name('clients.create');
    Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');
    Route::get('/clients/{client}/edit', [ClientController::class, 'edit'])->name('clients.edit');
    Route::put('/clients/{client}', [ClientController::class, 'update'])->name('clients.update');
    Route::delete('/clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');
    Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::get('/projects/{project}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
    Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::post('/projects/{project}/performance', [ProjectController::class, 'storePerformance'])->name('projects.performance.store');
    Route::post('/projects/{project}/audit', [ProjectController::class, 'runAudit'])->name('projects.audit.run');
    Route::get('/projects/{project}/audit/status', [ProjectController::class, 'auditStatus'])->name('projects.audit.status');
    Route::get('/projects/{project}/brief', [ProjectMarketingBriefController::class, 'edit'])->name('projects.brief.edit');
    Route::put('/projects/{project}/brief', [ProjectMarketingBriefController::class, 'update'])->name('projects.brief.update');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

    // Execution layer (Phase ج): recommendations → execution packages.
    Route::get('/projects/{project}/recommendations', [RecommendationController::class, 'index'])->name('projects.recommendations.index');
    Route::post('/projects/{project}/recommendations/{recommendation}/package', [RecommendationController::class, 'storePackage'])->name('projects.recommendations.package');
    Route::get('/projects/{project}/report', [ProjectReportController::class, 'show'])->name('projects.report');
    Route::get('/projects/{project}/report/pdf', [ProjectReportController::class, 'exportPdf'])
        ->middleware('entitlement:outputs.can_export')->name('projects.report.pdf');
    // دليل المشروع: الإجابات الخام مجمّعة كوثيقة قابلة للطباعة (خطوة مستقلة).
    Route::get('/projects/{project}/dossier', [ProjectDossierController::class, 'show'])->name('projects.dossier');
    Route::get('/projects/{project}/dossier/pdf', [ProjectDossierController::class, 'exportPdf'])
        ->middleware('entitlement:outputs.can_export')->name('projects.dossier.pdf');
    Route::get('/execution-packages/{executionPackage}', [ExecutionPackageController::class, 'show'])->name('execution-packages.show');
    Route::patch('/execution-packages/{executionPackage}/details', [ExecutionPackageController::class, 'updateDetails'])->name('execution-packages.details');
    Route::patch('/execution-packages/{executionPackage}/status', [ExecutionPackageController::class, 'updateStatus'])->name('execution-packages.status');
    Route::patch('/execution-packages/{executionPackage}/tasks/{executionTask}/details', [ExecutionPackageController::class, 'updateTaskDetails'])->name('execution-packages.tasks.details');
    Route::patch('/execution-packages/{executionPackage}/tasks/{executionTask}/status', [ExecutionPackageController::class, 'updateTaskStatus'])->name('execution-packages.tasks.status');
    Route::post('/execution-packages/{executionPackage}/reports', [ExecutionPackageController::class, 'storeReport'])->name('execution-packages.reports.store');

    // Agency white-label settings (Phase د) — gated by the white_label entitlement.
    Route::get('/agency/branding', [AgencyBrandingController::class, 'edit'])
        ->middleware('entitlement:white_label')->name('agency.branding.edit');
    Route::patch('/agency/branding', [AgencyBrandingController::class, 'update'])
        ->middleware('entitlement:white_label')->name('agency.branding.update');

    Route::patch('/account', [AccountController::class, 'update'])->name('account.update');
    Route::get('/team', [TeamController::class, 'index'])->name('team.index');
    Route::post('/team/invitations', [TeamController::class, 'invite'])->name('team.invitations.store');
    Route::post('/team/invitations/{token}/accept', [TeamController::class, 'accept'])->name('team.invitations.accept');
    Route::delete('/team/members/{member}', [TeamController::class, 'destroyMember'])->name('team.members.destroy');
    Route::delete('/team/invitations/{invitation}', [TeamController::class, 'destroyInvitation'])->name('team.invitations.destroy');
    Route::post('/projects/{project}/tools/{tool}/run', [ToolRunController::class, 'store'])->name('projects.tools.run');
    Route::post('/tools/{tool}/run', [ToolRunController::class, 'storeFromTool'])->name('tools.run');
    Route::get('/tools/{tool}', [WebToolController::class, 'show'])->name('tools.show');
    Route::post('/studio/generations', [StudioGenerationController::class, 'store'])
        ->middleware('entitlement:modules.ai_studio')
        ->name('studio.generations.store');
    Route::get('/studio/generations/{aiGeneration}/export/{format}', [StudioGenerationController::class, 'export'])
        ->where('format', 'md|markdown|html|pdf')
        ->middleware('entitlement:outputs.can_export')
        ->name('studio.generations.export');
    Route::get('/studio/generations/{aiGeneration}', [StudioGenerationController::class, 'show'])->name('studio.generations.show');
    Route::delete('/studio/generations/{aiGeneration}', [StudioGenerationController::class, 'destroy'])->name('studio.generations.destroy');
    Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');
    Route::post('/projects/{project}/approvals', [ApprovalController::class, 'store'])->name('projects.approvals.store');
    Route::patch('/approvals/{approval}', [ApprovalController::class, 'update'])->name('approvals.update');

    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('/billing/subscribe', [BillingController::class, 'subscribe'])->name('billing.subscribe');
    Route::get('/billing/paypal/return', [BillingController::class, 'paypalReturn'])->name('billing.paypal.return');
    Route::post('/billing/cancel-paypal', [BillingController::class, 'cancelPayPal'])->name('billing.paypal.cancel');
});

Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
Route::get('/account', [AccountController::class, 'edit'])->name('account.index');
Route::get('/tools', [ExperienceController::class, 'tools'])->name('tools.index');
Route::get('/studio', [ExperienceController::class, 'studio'])->name('studio.index');
Route::get('/templates', [ExperienceController::class, 'templates'])->name('templates.index');
Route::get('/reports', [ExperienceController::class, 'reports'])->name('reports.index');
Route::get('/agency', [ExperienceController::class, 'agency'])->name('agency.index');

Route::get('/pricing', [MarketingWebsiteController::class, 'pricing'])->name('pricing');
Route::get('/blog', [MarketingWebsiteController::class, 'blogIndex'])->name('blog.index');
Route::get('/blog/{slug}', [MarketingWebsiteController::class, 'blogShow'])->name('blog.show');
Route::get('/case-studies', [MarketingWebsiteController::class, 'caseStudiesIndex'])->name('case-studies.index');
Route::get('/case-studies/{slug}', [MarketingWebsiteController::class, 'caseStudyShow'])->name('case-studies.show');
Route::get('/community', [MarketingWebsiteController::class, 'communityIndex'])->name('community.index');
Route::get('/community/{slug}', [MarketingWebsiteController::class, 'communityShow'])->name('community.show');
Route::get('/contact', [MarketingWebsiteController::class, 'contact'])->name('contact');
Route::post('/contact', [ContactFormController::class, 'store'])->middleware('throttle:20,1')->name('contact.store');
Route::get('/privacy', [MarketingWebsiteController::class, 'privacy'])->name('privacy');
Route::get('/partnerships', [MarketingWebsiteController::class, 'partnerships'])->name('partnerships');
Route::get('/terms', [MarketingWebsiteController::class, 'terms'])->name('terms');

Route::middleware('auth')->prefix('api')->group(function (): void {
    Route::post('/tool/{tool}/run', [ToolRunApiController::class, 'store'])->name('api.tools.run');
    Route::get('/tool/{tool}/load', [ToolRunApiController::class, 'load'])->name('api.tools.load');
    Route::post('/ai/chat', [AiChatController::class, 'chat'])->middleware('throttle:ai-assist')->name('api.ai.chat');
    Route::post('/ai/analyze', [AiChatController::class, 'analyzeToolInputs'])->middleware('throttle:ai-assist')->name('api.ai.analyze');
    Route::post('/ai/suggest', [AiChatController::class, 'suggestFields'])->middleware('throttle:ai-assist')->name('api.ai.suggest');
    Route::post('/ai/research', [AiChatController::class, 'research'])->middleware('throttle:ai-assist')->name('api.ai.research');
});

Route::controller(PlatformController::class)->group(function (): void {
    Route::get('/', 'home')->name('home');
    Route::get('/about', 'about')->name('about');
    Route::get('/paths', 'show')->defaults('page', 'paths')->name('paths.index');
});
