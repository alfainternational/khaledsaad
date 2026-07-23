<?php

namespace Tests\Feature;

use App\Models\ContentFeedback;
use App\Models\GeoPack;
use App\Models\PersonaPanel;
use App\Models\Project;
use App\Models\PulseDigest;
use App\Models\Report;
use App\Models\ReportWatcher;
use App\Models\Task;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use App\Notifications\LiveReportChangedNotification;
use App\Notifications\WeeklyPulseNotification;
use App\Services\Growth\NextToolSuggester;
use App\Services\Projects\ProjectService;
use App\Services\Tools\ToolRunPipeline;
use App\Services\Tools\ToolRunService;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * محرك النمو المستمر: التقرير الحي، النبض، حزمة GEO، الجمهور الاصطناعي،
 * والتغذية الراجعة — القدرات التي تحوّل المنصة من «حملات» إلى «نمو مستمر».
 */
class GrowthEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ToolCatalogSeeder::class);

        config()->set('ai.deepseek', [
            'api_key' => 'test-key',
            'base_url' => 'https://api.deepseek.com',
            'model' => 'deepseek-v4-flash',
            'timeout' => 60,
            'tiers' => ['economy' => 'deepseek-v4-flash', 'standard' => 'deepseek-v4-flash', 'advanced' => 'deepseek-reasoner'],
        ]);
    }

    #[Test]
    public function a_user_can_make_a_report_live_and_gets_notified_only_when_inputs_change(): void
    {
        Notification::fake();

        [$user, $report] = $this->publishedReport();

        // التفعيل من صفحة التقرير.
        $this->actingAs($user)
            ->post(route('app.reports.watch', $report->id))
            ->assertRedirect();

        $watcher = ReportWatcher::where('report_id', $report->id)->first();
        $this->assertNotNull($watcher);
        $this->assertTrue($watcher->isActive());
        $this->assertNotSame('', $watcher->baseline_fingerprint);

        // فحص بلا تغيير: لا إشعار.
        $this->artisan('growth:watch')->assertSuccessful();
        Notification::assertNotSentTo($user, LiveReportChangedNotification::class);

        // تغيّر ما بُني عليه التقرير: وصف المشروع.
        $report->project->profile()->updateOrCreate([], [
            'description' => 'وصف جديد كليًا بعد تحوّل النشاط إلى التجارة الإلكترونية.',
        ]);

        $this->artisan('growth:watch')->assertSuccessful();

        Notification::assertSentTo($user, LiveReportChangedNotification::class);
        $watcher->refresh();
        $this->assertNotNull($watcher->last_changed_at);
        $this->assertNotEmpty($watcher->changes);

        // نفس الحالة لا تُنبَّه مرتين: البصمة المُشعَر عنها محفوظة.
        Notification::fake();
        $this->artisan('growth:watch')->assertSuccessful();
        Notification::assertNothingSent();
    }

    #[Test]
    public function the_weekly_pulse_surfaces_overdue_tasks_and_notifies_the_owner(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع النبض']);

        Task::create([
            'project_id' => $project->id,
            'title' => 'مهمة متأخرة',
            'status' => Task::STATUS_TODO,
            'due_date' => now()->subDays(3),
        ]);

        $this->artisan('growth:pulse')->assertSuccessful();

        $digest = PulseDigest::where('project_id', $project->id)->first();
        $this->assertNotNull($digest);
        $this->assertContains('overdue', array_column($digest->items, 'type'));
        $this->assertNotNull($digest->next_step, 'المتأخرات تفرض خطوة أسبوع.');

        Notification::assertSentTo($user, WeeklyPulseNotification::class);

        // الصفحة تعرض النبض.
        $this->actingAs($user)
            ->get(route('app.pulse.index'))
            ->assertOk()
            ->assertSee('مهمة متأخرة', false);
    }

    #[Test]
    public function the_geo_pack_requires_a_complete_profile_first(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع ناقص']);

        $this->actingAs($user)
            ->post(route('app.geo.generate', $project))
            ->assertSessionHasErrors('geo');

        $this->assertNull(GeoPack::where('project_id', $project->id)->first());
    }

    #[Test]
    public function the_geo_pack_ships_even_when_the_model_fails(): void
    {
        // المزود يفشل تمامًا: الأرضية الحتمية تبني الحزمة من حقائق الملف.
        Http::fake(fn () => Http::response('down', 500));

        $user = User::factory()->create();
        $project = $this->projectWithFullProfile($user, 'مشروع الحقائق');

        $this->actingAs($user)
            ->post(route('app.geo.generate', $project))
            ->assertRedirect(route('app.geo.show', $project));

        $pack = GeoPack::where('project_id', $project->id)->first();
        $this->assertNotNull($pack);
        $this->assertSame('rules', $pack->source);
        $this->assertNotEmpty($pack->faq);
        $this->assertSame('FAQPage', $pack->jsonld['faq_page']['@type']);
        $this->assertStringContainsString($project->name, $pack->llms_txt);

        // llms.txt يُنزَّل نصًا.
        $this->actingAs($user)
            ->get(route('app.geo.llms', $project))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    }

    #[Test]
    public function the_persona_panel_falls_back_to_declared_audiences_when_the_model_fails(): void
    {
        Http::fake(fn () => Http::response('down', 500));

        $user = User::factory()->create();
        $project = $this->projectWithFullProfile($user, 'مشروع الجمهور');
        $project->audiences()->create([
            'name' => 'أصحاب المتاجر الصغيرة',
            'pains' => 'ضيق الوقت، ضعف الميزانية',
        ]);

        $this->actingAs($user)
            ->post(route('app.audience.panel', $project))
            ->assertRedirect(route('app.audience.show', $project));

        $panel = PersonaPanel::where('project_id', $project->id)->first();
        $this->assertNotNull($panel);
        $this->assertSame('rules', $panel->source);
        $this->assertSame('أصحاب المتاجر الصغيرة', $panel->personas[0]['name']);
    }

    #[Test]
    public function testing_a_message_returns_scored_reactions_from_the_panel(): void
    {
        $user = User::factory()->create();
        $project = $this->projectWithFullProfile($user, 'مشروع الرسائل');

        Http::fake(fn () => Http::response([
            'model' => 'deepseek-v4-flash',
            'choices' => [['message' => ['content' => json_encode([
                'personas' => [
                    ['name' => 'سارة', 'age_range' => '25-34', 'role' => 'صاحبة مشروع منزلي', 'pains' => ['ضيق الوقت'], 'buying_style' => 'تقارن ثم تقرر', 'quote' => 'أريد نتيجة لا وعودًا.'],
                    ['name' => 'ماجد', 'age_range' => '35-44', 'role' => 'مدير تسويق', 'pains' => ['ضغط النتائج'], 'buying_style' => 'يطلب دليلًا', 'quote' => 'أرني الأرقام.'],
                    ['name' => 'هند', 'age_range' => '22-30', 'role' => 'مستقلة', 'pains' => ['الميزانية'], 'buying_style' => 'حساسة للسعر', 'quote' => 'كل ريال محسوب.'],
                ],
                'reactions' => [
                    ['persona' => 'سارة', 'score' => 74, 'reaction' => 'العرض واضح لكنها تريد ضمانًا صريحًا قبل الدفع.', 'objection' => 'ماذا لو لم ينفع معي؟'],
                    ['persona' => 'ماجد', 'score' => 55, 'reaction' => 'الرسالة عامة ولا تحمل رقمًا واحدًا يقنع مديره.', 'objection' => 'أين الدليل؟'],
                ],
                'overall' => [
                    'verdict' => 'الرسالة مفهومة لكنها تفتقد الدليل الملموس الذي يحسم التردد.',
                    'biggest_risk' => 'تجاهل الحساسين للدليل والسعر.',
                    'improved_version' => 'خلال 48 ساعة نسلّمك خطة قابلة للتنفيذ — وإن لم تنفعك نعيد المبلغ.',
                ],
            ], JSON_UNESCAPED_UNICODE)]]],
            'usage' => ['prompt_tokens' => 80, 'completion_tokens' => 60],
        ]));

        $this->actingAs($user)->post(route('app.audience.panel', $project));

        $this->actingAs($user)
            ->post(route('app.audience.test', $project), [
                'message' => 'خطة تسويق كاملة لمشروعك خلال 48 ساعة بسعر ثابت.',
            ])
            ->assertRedirect(route('app.audience.show', $project));

        $panel = PersonaPanel::where('project_id', $project->id)->first();
        $test = $panel->tests()->first();
        $this->assertNotNull($test);
        $this->assertSame(74, $test->results['reactions'][0]['score']);
        $this->assertNotEmpty($test->results['overall']['improved_version']);
    }

    #[Test]
    public function feedback_is_one_verdict_per_user_that_can_change_its_mind(): void
    {
        [$user, $report] = $this->publishedReport();

        $this->actingAs($user)
            ->post(route('app.reports.feedback', $report->id), ['verdict' => 'up'])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('app.reports.feedback', $report->id), ['verdict' => 'down'])
            ->assertRedirect();

        $this->assertSame(1, ContentFeedback::where('subject_id', $report->id)->count());
        $this->assertSame('down', ContentFeedback::where('subject_id', $report->id)->value('verdict'));
    }

    #[Test]
    public function the_next_tool_suggestion_starts_the_journey_and_skips_used_tools(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع الرحلة']);

        // مشروع بلا تقارير: البداية الطبيعية هي تشخيص الجاهزية.
        $suggestion = app(NextToolSuggester::class)->suggest($project);
        $this->assertNotNull($suggestion);
        $this->assertSame('marketing-score', $suggestion['tool']->key);

        // بعد تقرير لتشخيص الجاهزية: يُقترح ما يليه لا ما سبق.
        [, $report] = $this->publishedReport($user, $project);
        $next = app(NextToolSuggester::class)->suggest($project->fresh());
        $this->assertNotNull($next);
        $this->assertNotSame('marketing-score', $next['tool']->key);
    }

    /**
     * تقرير منشور عبر خط الأنابيب الحقيقي (بمزود مُحاكى) — نفس مسار الإنتاج.
     *
     * @return array{0: User, 1: Report}
     */
    private function publishedReport(?User $user = null, ?Project $project = null): array
    {
        $this->fakePipelineProvider();

        $user ??= User::factory()->create();
        $project ??= app(ProjectService::class)->create($user, ['name' => 'مشروع التقرير']);
        $tool = Tool::where('key', 'marketing-score')->firstOrFail();

        $run = app(ToolRunService::class)->start($project, $tool, $user);

        $answers = [
            1 => ['business_model' => 'services', 'description' => str_repeat('وصف واضح للخدمة المقدمة ', 3), 'geography' => 'الرياض', 'monthly_budget' => 5000],
            2 => ['primary_goal' => 'leads', 'value_proposition' => 'نسلّم خلال 48 ساعة أو المبلغ يُعاد', 'audience_clarity' => 'documented'],
            3 => ['active_channels' => ['seo', 'paid'], 'tracking_maturity' => 'basic', 'content_cadence' => 'weekly'],
            4 => ['landing_experience' => 'basic', 'retention_motion' => 'manual', 'known_cac' => 120],
        ];

        foreach ($answers as $step => $input) {
            app(ToolRunService::class)->saveStep($run, $step, $input);
        }

        app(ToolRunPipeline::class)->handle($run->refresh());

        $report = $run->refresh()->report;
        $this->assertNotNull($report, 'خط الأنابيب يجب أن ينتج تقريرًا.');

        Http::clearResolvedInstances();

        return [$user, $report];
    }

    private function projectWithFullProfile(User $user, string $name): Project
    {
        $project = app(ProjectService::class)->create($user, ['name' => $name, 'industry' => 'التجارة الإلكترونية']);

        $project->profile()->updateOrCreate([], [
            'business_model' => 'services',
            'description' => 'متجر متخصص في المنتجات اليدوية السعودية بجودة تُوثَّق بالصور الحقيقية.',
            'geography' => 'السعودية والخليج',
            'website' => 'https://example.sa',
            'value_proposition' => 'منتجات يدوية أصلية تصل خلال ثلاثة أيام.',
        ]);

        return $project->fresh();
    }

    private function fakePipelineProvider(): void
    {
        Http::fake(fn () => Http::response([
            'model' => 'deepseek-v4-flash',
            'choices' => [['message' => ['content' => json_encode([
                'missing' => [], 'conflicts' => [], 'issues' => [],
                'headline' => 'عنوان تحليلي واضح للقسم',
                'points' => [['text' => 'نقطة مبنية على الإجابات مباشرة.', 'evidence' => 'الإجابات', 'is_assumption' => false]],
                'summary' => 'ملخص تنفيذي يوضح الوضع الحالي وأهم ما يجب فعله في التسعين يومًا القادمة.',
                'confidence' => 70,
                'assumptions' => [],
                'next_step' => ['title' => 'ركّب قياس التحويل', 'description' => 'عرّف حدث تحويل واحدًا واربطه بمصدر الزيارة.'],
                'findings' => [[
                    'title' => 'القياس لا يصل إلى الإيراد',
                    'description' => 'التتبع الحالي يسجل الزيارات فقط دون ربطها بالمبيعات.',
                    'category' => 'القياس',
                    'severity' => 'high',
                    'evidence' => 'إجابة حالة القياس',
                    'confidence' => 90,
                    'is_assumption' => false,
                    'recommendations' => [[
                        'title' => 'عرّف أحداث التحويل',
                        'description' => 'أضف أحداث نموذج وواتساب وشراء واربطها بمصدرها.',
                        'impact' => 'high',
                        'effort' => 'low',
                    ]],
                ]],
            ], JSON_UNESCAPED_UNICODE)]]],
            'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 60],
        ]));
    }
}
