<?php

use App\Http\Controllers\Admin\AdminConsultationController;
use App\Http\Controllers\Admin\AdminContentCategoryController;
use App\Http\Controllers\Admin\AdminContentController;
use App\Http\Controllers\Admin\AdminContentMediaController;
use App\Http\Controllers\Admin\AdminContentSubscriberController;
use App\Http\Controllers\Admin\AdminCourseCurriculumController;
use App\Http\Controllers\Admin\AdminCreditPackController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminFeatureController;
use App\Http\Controllers\Admin\AdminGatewayController;
use App\Http\Controllers\Admin\AdminInsightsController;
use App\Http\Controllers\Admin\AdminManualReportController;
use App\Http\Controllers\Admin\AdminPaymentController;
use App\Http\Controllers\Admin\AdminPlanController;
use App\Http\Controllers\Admin\AdminReportingGapController;
use App\Http\Controllers\Admin\AdminSettingsController;
use App\Http\Controllers\Admin\AdminToolController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\OperationsController;
use App\Http\Controllers\Admin\UsageController;
use App\Http\Controllers\App\AgencyReportController;
use App\Http\Controllers\App\AudienceLabController;
use App\Http\Controllers\App\BillingController;
use App\Http\Controllers\App\CheckoutController;
use App\Http\Controllers\App\CompetitorController;
use App\Http\Controllers\App\ConsultationController;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\ExperienceController;
use App\Http\Controllers\App\FeedbackController;
use App\Http\Controllers\App\GeoPackController;
use App\Http\Controllers\App\KpiController;
use App\Http\Controllers\App\LegacyMarketingLearningController;
use App\Http\Controllers\App\MarketingCourseController;
use App\Http\Controllers\App\MessageStudioController;
use App\Http\Controllers\App\NotificationController;
use App\Http\Controllers\App\PlanController;
use App\Http\Controllers\App\PortfolioController;
use App\Http\Controllers\App\PresenceController;
use App\Http\Controllers\App\ProjectController;
use App\Http\Controllers\App\ProspectController;
use App\Http\Controllers\App\PulseController;
use App\Http\Controllers\App\QuestionAssistController;
use App\Http\Controllers\App\ReadinessController;
use App\Http\Controllers\App\ReportController;
use App\Http\Controllers\App\ReportGapController;
use App\Http\Controllers\App\ReportIndexController;
use App\Http\Controllers\App\ReportWatchController;
use App\Http\Controllers\App\SearchController;
use App\Http\Controllers\App\SecurityController;
use App\Http\Controllers\App\TaskController;
use App\Http\Controllers\App\ToolCatalogController;
use App\Http\Controllers\App\ToolRunController;
use App\Http\Controllers\App\VoiceIntakeController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\InsightsCollectorController;
use App\Http\Controllers\Site\ContentLibraryController;
use App\Http\Controllers\Site\ContentMediaController;
use App\Http\Controllers\Site\ContentResourceController;
use App\Http\Controllers\Site\ContentSubscriptionController;
use App\Http\Controllers\Site\GuestRunController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\LegalController;
use App\Http\Controllers\Site\MobileAppController;
use App\Http\Controllers\Site\PricingController;
use App\Http\Controllers\Site\ProfilePdfController;
use App\Http\Controllers\Site\PublicPageController;
use App\Http\Controllers\Site\SectorLandingController;
use App\Http\Controllers\Site\SharedAgencyReportController;
use App\Http\Controllers\Site\SharedReportController;
use App\Http\Controllers\Site\ToolShowcaseController;
use App\Http\Controllers\Webhooks\MoyasarWebhookController;
use App\Http\Controllers\Webhooks\PayPalWebhookController;
use App\Http\Controllers\Webhooks\TapWebhookController;
use App\Models\Content;
use App\Models\Tool;
use App\Modules\Shared\I18n\LocaleRegistry;
use App\Modules\Shared\I18n\LocaleUrls;
use App\Modules\Shared\Sectors\Sector;
use App\Support\Billing\FeatureKey;
use Illuminate\Support\Facades\Route;

/*
 * نقطة جمع الإحصاءات — الطبقة الثانية بعد التقاط الخادم.
 *
 * السقف مرتفع عمدًا (١٢٠ في الدقيقة): نبضة كل خمس عشرة ثوان من عدة
 * تبويبات مفتوحة تصل بسرعة إلى عشرات الطلبات، وسقفٌ ضيّق هنا يقطع
 * قياس الزمن عن أطول الجلسات — أي عن أهمّ الزوّار تحديدًا.
 */
Route::post('_insights/collect', InsightsCollectorController::class)
    ->middleware('throttle:120,1')
    ->name('insights.collect');

Route::get('/', HomeController::class)->name('home');
Route::get('profile', [PublicPageController::class, 'profile'])->name('profile');
Route::get('profile.pdf', ProfilePdfController::class)->name('profile.pdf');
Route::permanentRedirect('cv', '/profile');
Route::get('services', [PublicPageController::class, 'services'])->name('services');
Route::get('methodology', [PublicPageController::class, 'methodology'])->name('methodology');
Route::get('principles', [PublicPageController::class, 'principles'])->name('principles');
Route::get('knowledge', [PublicPageController::class, 'knowledge'])->name('knowledge');
Route::get('faq', [PublicPageController::class, 'faq'])->name('faq');
Route::get('sample-report', [PublicPageController::class, 'sampleReport'])->name('sample-report');
Route::get('download/android', MobileAppController::class)->name('mobile.download');
Route::get('blog', [ContentLibraryController::class, 'index'])->name('content.index');
Route::get('blog/media/{media}', ContentMediaController::class)->name('content.media.show');
Route::get('blog/{content}/resources/{resource}', ContentResourceController::class)->name('content.resources.download');
Route::get('blog/{content}', [ContentLibraryController::class, 'show'])->name('content.show');
Route::post('blog/{content}/subscribe', [ContentSubscriptionController::class, 'store'])->middleware('throttle:8,1')->name('content.subscribe');

// واجهة الأدوات العامة: يراها الزائر قبل التسجيل ليعرف ما الذي سيدخل إليه.
Route::get('tools', [ToolShowcaseController::class, 'index'])->name('tools.index');
Route::get('pricing', [PricingController::class, 'index'])->name('pricing');
Route::get('llms.txt', function () {
    $lessons = Content::query()
        ->published()
        ->where('source_key', 'like', 'marketing-course-%')
        ->orderBy('learning_order')
        ->get();

    /*
     * القطاعات في الملف لا في الشيفرة: `llms.txt` يصف ما تفعله المنصة
     * لنموذج لغوي، والتخصص أهم ما يميّزها. قائمة يدوية هنا تنجرف عن
     * `Sector::SPECIALIZED` بلا أن يلاحظ أحد.
     */
    $sectors = collect(Sector::SPECIALIZED)
        ->map(fn (string $sector): array => [
            'label' => Sector::label($sector),
            'url' => route('sectors.show', $sector),
        ])
        ->all();

    return response()
        ->view('site.content.llms', compact('lessons', 'sectors'))
        ->header('Content-Type', 'text/plain; charset=UTF-8');
})->name('llms');

/*
 * صفحات القطاعات الثلاثة: التخصص المعلن يحتاج صفحةً تُثبته، لا جملةً في
 * الهيرو. المسار محصور بالقطاعات المتخصصة نفسها فلا تُخترع صفحة لقطاع
 * لا عمق لنا فيه.
 */
Route::get('sectors', [SectorLandingController::class, 'index'])->name('sectors.index');
Route::get('sectors/{sector}', [SectorLandingController::class, 'show'])
    ->whereIn('sector', Sector::SPECIALIZED)
    ->name('sectors.show');

// خريطة الموقع (بند ١٦): الصفحات العامة + صفحات الأدوات — من قاعدة البيانات
// لا من قائمة يدوية تنجرف. كاش ساعة لأنها لا تتغير إلا ببذر أو إصدار.
Route::get('sitemap.xml', function () {
    $xml = cache()->remember('sitemap.xml', now()->addHour(), function (): string {
        $sitemapUrls = collect([
            ['loc' => route('home'), 'priority' => '1.0'],
            ['loc' => route('profile'), 'priority' => '0.9'],
            ['loc' => route('services'), 'priority' => '0.9'],
            ['loc' => route('methodology'), 'priority' => '0.8'],
            ['loc' => route('principles'), 'priority' => '0.7'],
            ['loc' => route('knowledge'), 'priority' => '0.9'],
            ['loc' => route('faq'), 'priority' => '0.7'],
            ['loc' => route('sample-report'), 'priority' => '0.7'],
            ['loc' => route('tools.index'), 'priority' => '0.9'],
            ['loc' => route('pricing'), 'priority' => '0.9'],
            ['loc' => route('content.index'), 'priority' => '0.9'],
            ['loc' => route('mobile.download'), 'priority' => '0.6'],
        ])->merge(
            // صفحات القطاعات في الخريطة: التخصص لا يُعثر عليه إن لم يُفهرس.
            collect([['loc' => route('sectors.index'), 'priority' => '0.9']])->merge(
                collect(Sector::SPECIALIZED)
                    ->map(fn (string $sector) => ['loc' => route('sectors.show', $sector), 'priority' => '0.9']),
            ),
        )->merge(
            Tool::orderBy('sort_order')->get()
                ->map(fn ($tool) => [
                    'loc' => route('tools.show', $tool->key),
                    'priority' => '0.8',
                    'lastmod' => $tool->updated_at?->toAtomString(),
                ]),
        )->merge(
            Content::query()->published()->orderByDesc('published_at')->get()
                ->map(fn ($content) => [
                    'loc' => route('content.show', $content),
                    'priority' => $content->type === Content::TYPE_COURSE ? '0.9' : '0.8',
                    'lastmod' => $content->updated_at?->toAtomString(),
                ]),
        );

        /*
         * كل رابط يُعلن نسخه بكل لغة داخل عنصره.
         *
         * `hreflang` في الصفحة يخبر جوجل بالبدائل بعد أن يزورها. وإعلانها
         * في الخريطة يخبره **قبل** أن يزور أيًّا منها، فيكتشف النسخ الأخرى
         * ولا ينتظر أن يعثر عليها بالزحف. وبلا هذا تبقى الإنجليزية
         * والفرنسية معلومتين للزائر ومجهولتين للفهرس.
         *
         * الشرط الذي يُسقط القيمة كلها إن اختلّ: كل رابط بديل هنا يجب أن
         * يطابق `canonical` تلك الصفحة حرفيًّا — ولذلك يُبنى الاثنان من
         * `LocaleUrls` نفسه.
         */
        $urls = app(LocaleUrls::class);
        $locales = app(LocaleRegistry::class);
        $enabled = $locales->enabled();

        $entries = $sitemapUrls->map(function (array $url) use ($urls, $locales, $enabled): string {
            $lastmod = isset($url['lastmod']) && $url['lastmod'] ? "<lastmod>{$url['lastmod']}</lastmod>" : '';

            $alternates = '';

            if (count($enabled) > 1) {
                foreach ($enabled as $code) {
                    $href = htmlspecialchars($urls->absolute($url['loc'], $code), ENT_XML1);
                    $alternates .= '<xhtml:link rel="alternate" hreflang="'.$locales->htmlLang($code).'" href="'.$href.'"/>';
                }

                $default = htmlspecialchars($urls->absolute($url['loc'], $locales->source()), ENT_XML1);
                $alternates .= '<xhtml:link rel="alternate" hreflang="x-default" href="'.$default.'"/>';
            }

            $loc = htmlspecialchars($url['loc'], ENT_XML1);

            return "<url><loc>{$loc}</loc>{$lastmod}<priority>{$url['priority']}</priority>{$alternates}</url>";
        })->implode('');

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'
            .$entries.'</urlset>';
    });

    return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
})->name('sitemap');
Route::get('tools/{tool}', [ToolShowcaseController::class, 'show'])->name('tools.show');

// التجربة بلا حساب: يجرّب أولًا ثم يقرر التسجيل.
Route::middleware('throttle:12,60')->group(function (): void {
    Route::post('try/{tool}', [GuestRunController::class, 'start'])->name('try.start');
});

/*
 * نفس العنوان بـ GET: صفحة الأداة لا رسالة ٤٠٥.
 *
 * الزر الرئيسي في الصفحة الرئيسية نموذج POST، وعنوانه يصل بـ GET في
 * ثلاث حالات عادية: تحديث الصفحة بعد الإرسال، ومشاركة الرابط، وزحف
 * البوت على `action` النموذج. وكان الردّ في الثلاث «405 Method Not
 * Allowed» خامًا — أسوأ ما يمكن أن يراه زائر ضغط أول زر في الموقع.
 */
Route::get('try/{tool}', fn (Tool $tool) => redirect()->route('tools.show', $tool->key))
    ->name('try.show');
Route::get('try/{run}/steps/{step}', [GuestRunController::class, 'step'])->name('try.step');
Route::post('try/{run}/steps/{step}', [GuestRunController::class, 'saveStep'])->name('try.step.save');
Route::get('try/{run}/result', [GuestRunController::class, 'result'])->name('try.result');

/*
 * موجز الوكالة عبر رابط المشاركة: عام بلا تسجيل دخول، لكنه محدود بالمعدل
 * كي لا يُستخدم لتخمين الرموز، وبلا فهرسة في محركات البحث.
 */
Route::middleware('throttle:30,1')->group(function (): void {
    // تقرير للقراءة فقط برابط موقّع مؤقت — بلا حساب (بند ١٨).
    Route::get('r/report/{report}', [SharedReportController::class, 'show'])
        ->middleware('signed')->name('shared.report');

    Route::get('r/{token}', [SharedAgencyReportController::class, 'show'])->name('shared.agency-report');
    Route::get('r/{token}/pdf', [SharedAgencyReportController::class, 'pdf'])->name('shared.agency-report.pdf');
    Route::get('r/{token}/data.json', [SharedAgencyReportController::class, 'data'])->name('shared.agency-report.data');
});

// الصفحات القانونية بلغة مفهومة، لا روابط تعيدك إلى الأسئلة الشائعة.
Route::get('privacy', LegalController::class)->defaults('page', 'privacy')->name('privacy');
Route::get('terms', LegalController::class)->defaults('page', 'terms')->name('terms');

Route::middleware('guest')->group(function (): void {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');

    /*
     * أبواب الاعتماد محدودة بالمعدّل — وكانت مفتوحة بلا سقف.
     *
     * القياس كشف ١٩ محاولة دخول من غير الإدارة على منصة فيها حساب واحد،
     * كلها في شهر واحد ومن عميل يشغّل جافاسكربت. هذا حشو بيانات اعتماد
     * لا زوّار حائرون، ولم يكن يوقفه شيء.
     *
     * السقف على الزوج (المسار + العنوان) لا على البريد وحده: الربط
     * بالبريد يجعل المهاجم يبدّله في كل محاولة فيتجاوز السقف كله.
     */
    Route::middleware('throttle:10,1')->group(function (): void {
        Route::post('register', [RegisteredUserController::class, 'store']);
        Route::post('login', [AuthenticatedSessionController::class, 'store']);
    });

    // خطوة التحقق الثانية بالبريد (بند ٢٣) — لمن فعّلها فقط.
    Route::get('login/code', [AuthenticatedSessionController::class, 'otpForm'])->name('login.otp');
    Route::post('login/code', [AuthenticatedSessionController::class, 'otpVerify'])
        ->middleware('throttle:6,1')->name('login.otp.verify');

    // من نسي كلمة مروره كان يخرج من المنتج نهائيًا قبل هذا المسار.
    Route::get('forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'email'])
        ->middleware('throttle:6,1')->name('password.email');
    Route::get('reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'update'])
        ->middleware('throttle:6,1')->name('password.update');
});

Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')->name('logout');

Route::middleware(['auth', 'experience-access'])->prefix('app')->name('app.')->group(function (): void {
    Route::get('experience', [ExperienceController::class, 'choose'])->name('experience.choose');
    Route::post('experience', [ExperienceController::class, 'select'])->name('experience.select');
    Route::get('experience/{experience}/activate', [ExperienceController::class, 'activation'])->name('experience.activate');
    Route::post('experience/{experience}/activate', [ExperienceController::class, 'enable'])->name('experience.enable');
    Route::post('experience/{experience}/switch', [ExperienceController::class, 'switch'])->name('experience.switch');

    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('learn/marketing', [MarketingCourseController::class, 'index'])->name('learning.marketing.home');
    Route::get('learn/marketing/{exercise}', [MarketingCourseController::class, 'exercise'])->name('learning.marketing.course.exercise');
    Route::put('learn/marketing/{exercise}', [MarketingCourseController::class, 'save'])->name('learning.marketing.course.save');
    Route::post('learn/marketing/{exercise}/review', [MarketingCourseController::class, 'submit'])->middleware('throttle:20,60')->name('learning.marketing.course.submit');
    Route::get('learn/marketing/{exercise}/result', [MarketingCourseController::class, 'result'])->name('learning.marketing.course.result');
    Route::post('learn/marketing/{exercise}/retry', [MarketingCourseController::class, 'retry'])->middleware('throttle:10,60')->name('learning.marketing.course.retry');
    Route::post('learn/marketing/{exercise}/questions/{question}/assist', [MarketingCourseController::class, 'assist'])->middleware('throttle:10,1')->name('learning.marketing.course.assist');

    Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::get('projects/{project}/learn/marketing', [LegacyMarketingLearningController::class, 'index'])->name('learning.marketing.index');
    Route::get('projects/{project}/learn/marketing/{exercise}', [LegacyMarketingLearningController::class, 'exercise'])->name('learning.marketing.exercise');
    Route::put('projects/{project}/learn/marketing/{exercise}', [LegacyMarketingLearningController::class, 'save'])->name('learning.marketing.save');
    Route::post('projects/{project}/learn/marketing/{exercise}/review', [LegacyMarketingLearningController::class, 'submit'])->middleware('throttle:20,60')->name('learning.marketing.submit');
    Route::get('projects/{project}/learn/marketing/{exercise}/result', [LegacyMarketingLearningController::class, 'result'])->name('learning.marketing.result');
    Route::post('projects/{project}/learn/marketing/{exercise}/retry', [LegacyMarketingLearningController::class, 'retry'])->middleware('throttle:10,60')->name('learning.marketing.retry');
    Route::get('projects/{project}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
    Route::put('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::get('projects/{project}/consultations', [ConsultationController::class, 'project'])->name('consultations.project');
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

    /*
     * ذكاء المدخلات: دليل ومقترحات تخصّ هذا النشاط، وقياس كفاية ما كتبه.
     *
     * حدّان مختلفان لأن التكلفتين مختلفتان: التوليد يستدعي نموذجًا لغويًّا فيُحدّ
     * بعشرين طلبًا في الساعة ويُحجز له من سقف المساحة، والقياس حتميّ محليّ بلا
     * تكلفة فيُترك للكتابة اللحظية بحدٍّ واسع.
     */
    Route::post('projects/{project}/assist', [QuestionAssistController::class, 'store'])
        ->middleware('throttle:30,60')->name('assist.store');
    Route::post('projects/{project}/answer-fitness', [QuestionAssistController::class, 'fitness'])
        ->middleware('throttle:240,60')->name('assist.fitness');

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

    // البحث الشامل في أسطح المستخدم الأربعة (بند ٢٥).
    Route::get('search', [SearchController::class, 'index'])->name('search');

    // إنهاء الانتحال: يستدعيه الآدمن وهو داخل حساب المستخدم (بند ٢١).
    Route::post('impersonation/stop', [ImpersonationController::class, 'stop'])->name('impersonation.stop');

    // أمان الحساب: خطوة التحقق الثانية + الأجهزة المتصلة (بند ٢٣).
    Route::get('security', [SecurityController::class, 'index'])->name('security');
    Route::post('security/otp', [SecurityController::class, 'toggleOtp'])->name('security.otp');
    Route::post('security/logout-others', [SecurityController::class, 'logoutOthers'])->name('security.logout-others');

    /*
     * «الخطة والمهام» و«تقاريري» عبر المشاريع كلها.
     *
     * كان عنصرا الملاحة هذان يشيران إلى `projects.index` لأن القسمين لم
     * يكونا مبنيَّين — فكانت القائمة تعد بثلاثة أبواب تفتح كلها على باب
     * واحد. المسار الحقيقي هو الإصلاح؛ تعطيل الرابط كان سيصدُق ولا يكفي.
     */
    Route::get('plan', [PlanController::class, 'index'])->name('plan');
    Route::get('reports', [ReportIndexController::class, 'index'])->name('reports.index');

    Route::get('projects/{project}/tasks', [TaskController::class, 'index'])->name('projects.tasks');
    Route::patch('tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::post('tasks/{task}/develop', [TaskController::class, 'develop'])->name('tasks.develop');
    Route::post('tasks/{task}/adopt', [TaskController::class, 'adopt'])->name('tasks.adopt');

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

    /*
     * سدّ فجوات التقرير: الباب الذي كان ينقص «معلومات تحتاج إلى تأكيد منك».
     *
     * لا بوابة ميزة عليه عمدًا. إكمال بياناته حقّ صاحب النشاط لا ميزة تُباع،
     * وحجبه خلف اشتراك يعني بيع الحلّ لمشكلة صنعناها بإعلان النقص.
     */
    Route::get('reports/{report}/gaps', [ReportGapController::class, 'edit'])->name('reports.gaps.edit');
    Route::put('reports/{report}/gaps', [ReportGapController::class, 'update'])
        ->middleware('throttle:30,60')->name('reports.gaps.update');

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

    // مختبر الجمهور الاصطناعي واستوديو الرسائل — خلف الميزة نفسها.
    Route::middleware('feature:'.FeatureKey::AUDIENCE_LAB)->group(function (): void {
        Route::get('projects/{project}/audience-lab', [AudienceLabController::class, 'show'])->name('audience.show');
        Route::post('projects/{project}/audience-lab/panel', [AudienceLabController::class, 'buildPanel'])
            ->middleware('throttle:6,60')->name('audience.panel');
        Route::post('projects/{project}/audience-lab/tests', [AudienceLabController::class, 'test'])
            ->middleware('throttle:10,60')->name('audience.test');

        // الاستوديو: الشخصية وحدة العمل — تبويب ومسودة وإصدارات لكل واحدة.
        Route::get('projects/{project}/message-studio', [MessageStudioController::class, 'show'])
            ->name('messages.studio');
        Route::post('projects/{project}/message-studio/panel', [MessageStudioController::class, 'buildPanel'])
            ->middleware('throttle:6,60')->name('messages.panel');
        Route::post('projects/{project}/message-studio/suggest', [MessageStudioController::class, 'suggest'])
            ->middleware('throttle:10,60')->name('messages.suggest');
        Route::post('projects/{project}/message-studio/variants', [MessageStudioController::class, 'store'])
            ->name('messages.store');
        Route::post('projects/{project}/message-studio/tests', [MessageStudioController::class, 'test'])
            ->middleware('throttle:10,60')->name('messages.test');
        Route::post('projects/{project}/message-studio/results/{result}/revise', [MessageStudioController::class, 'revise'])
            ->name('messages.revise');
        Route::patch('projects/{project}/message-studio/variants/{variant}', [MessageStudioController::class, 'updateStatus'])
            ->name('messages.status');

        // العملاء المتوقعون: رسالة باسم كل واحد منهم.
        Route::get('projects/{project}/prospects', [ProspectController::class, 'index'])->name('prospects.index');
        Route::post('projects/{project}/prospects', [ProspectController::class, 'store'])->name('prospects.store');
        Route::post('projects/{project}/prospects/messages', [ProspectController::class, 'generate'])
            ->middleware('throttle:10,60')->name('prospects.generate');
        Route::post('projects/{project}/prospects/{prospect}/messages', [ProspectController::class, 'storeMessage'])
            ->name('prospects.messages.store');
        Route::patch('projects/{project}/prospects/{prospect}', [ProspectController::class, 'updateProspect'])
            ->name('prospects.update');
        Route::patch('projects/{project}/prospect-messages/{message}', [ProspectController::class, 'markSent'])
            ->name('prospects.messages.sent');
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
    Route::get('content-subscribers', [AdminContentSubscriberController::class, 'index'])->name('content-subscribers.index');
    Route::get('content-subscribers/export', [AdminContentSubscriberController::class, 'export'])->name('content-subscribers.export');
    Route::patch('content-subscribers/{subscriber}/status', [AdminContentSubscriberController::class, 'updateStatus'])->name('content-subscribers.status');
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('usage', UsageController::class)->name('usage');

    /*
     * إحصاءات الزوّار: مصدرها هذا الخادم وحده، بلا سكربت خارجي.
     * الترتيب مقصود — `live` و`export` قبل `{visitor}` وإلا ابتلعهما
     * المُعامل الحرّ وصارا «زائرًا اسمه live».
     */
    Route::get('insights', [AdminInsightsController::class, 'index'])->name('insights');
    Route::get('insights/live', [AdminInsightsController::class, 'live'])->name('insights.live');
    Route::get('insights/export', [AdminInsightsController::class, 'export'])->name('insights.export');
    Route::get('insights/visitors', [AdminInsightsController::class, 'visitors'])->name('insights.visitors');
    Route::get('insights/visitors/{visitor}', [AdminInsightsController::class, 'visitor'])->name('insights.visitor');
    Route::get('insights/sessions/{session}', [AdminInsightsController::class, 'session'])->name('insights.session');
    Route::get('content-media', [AdminContentMediaController::class, 'index'])->name('content-media.index');
    Route::delete('content-media/{media}', [AdminContentMediaController::class, 'destroy'])->name('content-media.destroy');
    Route::post('content/media', [AdminContentMediaController::class, 'store'])->name('content.media.store');
    Route::resource('content-categories', AdminContentCategoryController::class)->except('show');
    Route::resource('content', AdminContentController::class)->except(['show', 'destroy']);
    Route::get('content/{course}/curriculum', [AdminCourseCurriculumController::class, 'show'])->name('content.curriculum');
    Route::post('content/{course}/curriculum/sections', [AdminCourseCurriculumController::class, 'storeSection'])->name('content.sections.store');
    Route::put('content/{course}/curriculum/sections/{section}', [AdminCourseCurriculumController::class, 'updateSection'])->name('content.sections.update');
    Route::delete('content/{course}/curriculum/sections/{section}', [AdminCourseCurriculumController::class, 'destroySection'])->name('content.sections.destroy');
    Route::post('content/{course}/curriculum/sections/{section}/items', [AdminCourseCurriculumController::class, 'storeItem'])->name('content.sections.items.store');
    Route::delete('content/{course}/curriculum/sections/{section}/items/{item}', [AdminCourseCurriculumController::class, 'destroyItem'])->name('content.sections.items.destroy');
    Route::patch('content/{content}/archive', [AdminContentController::class, 'archive'])->name('content.archive');
    Route::patch('content/{content}/restore', [AdminContentController::class, 'restore'])->name('content.restore');
    Route::post('content/{content}/learning-update', [AdminContentController::class, 'draftLearningUpdate'])->name('content.learning-update');

    Route::get('consultations', [AdminConsultationController::class, 'index'])->name('consultations.index');
    Route::get('consultations/versions/{version}', [AdminConsultationController::class, 'show'])->name('consultations.show');
    Route::post('consultations/{blueprint}/drafts', [AdminConsultationController::class, 'createDraft'])->name('consultations.drafts.store');
    Route::put('consultations/versions/{version}/questions/{question}', [AdminConsultationController::class, 'updateQuestion'])->name('consultations.questions.update');
    Route::post('consultations/versions/{version}/publish', [AdminConsultationController::class, 'publish'])->name('consultations.publish');
    Route::get('consultations/versions/{version}/simulate', [AdminConsultationController::class, 'simulate'])->name('consultations.simulate');
    /*
     * فجوات التقارير: قوالب غائبة وبيانات ناقصة.
     *
     * `template_gaps` كان يُكتب ولا يُقرأ — يسجّل النظام عجزه عن بناء ورقة
     * ولا يفتح الجدولَ أحد. هذه الشاشة هي الطرف القارئ.
     */
    Route::get('reporting-gaps', [AdminReportingGapController::class, 'index'])->name('reporting-gaps.index');

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
    Route::post('tools/{tool}/release', [AdminToolController::class, 'release'])->name('tools.release');

    // غرفة العمليات: الصحة والقمع والتدقيق (بنود ٢٠ و٢٢ و٣٠).
    Route::get('operations', [OperationsController::class, 'index'])->name('operations');

    // انتحال مستخدم لخدمة العملاء (بند ٢١) — الإيقاف خارج مجموعة admin
    // لأن المنتحِل يتصفح بحساب المستخدم لا كآدمن.
    Route::post('users/{user}/impersonate', [ImpersonationController::class, 'start'])->name('users.impersonate');

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
