<?php

namespace Tests\Feature;

use App\Models\Finding;
use App\Models\Kpi;
use App\Models\Project;
use App\Models\ProjectAnswer;
use App\Models\ProjectCompetitor;
use App\Models\Recommendation;
use App\Models\Report;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use App\Services\Projects\ProjectService;
use App\Services\Reports\AgencyReportService;
use App\Services\Reports\AgencyStateLedger;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * المستند غرضه أن يغني الوكالة عن إعادة الاستكشاف. لذلك ما تختبره هذه
 * المجموعة ليس «هل يُنتج ملفًا» بل «هل يصل كل ما يعرفه المشروع إلى الورقة».
 */
class AgencyStateDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ToolCatalogSeeder::class);
    }

    #[Test]
    public function the_ledger_groups_answers_by_theme_and_keeps_every_answered_field(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع الدفتر']);
        $project->profile()->updateOrCreate([], ['monthly_budget' => 9000]);

        $this->answer($project, 'value_proposition', 'توصيل خلال ساعتين', 'offer-builder');
        $this->answer($project, 'best_customer', 'أمهات عاملات', 'audience-map');
        $this->answer($project, 'مفتاح_غير_معروف', 'قيمة لا تنتمي لمحور', 'tool-x');

        $ledger = app(AgencyStateLedger::class)->build($project->fresh());
        $themes = collect($ledger['themes'])->keyBy('key');

        $this->assertContains(
            'توصيل خلال ساعتين',
            array_column($themes['offer']['answered'], 'value'),
        );
        $this->assertContains(
            'أمهات عاملات',
            array_column($themes['audience']['answered'], 'value'),
        );

        // القاعدة الحاسمة: لا إجابة تسقط لأن مفتاحها غير مصنّف.
        $this->assertContains(
            'قيمة لا تنتمي لمحور',
            array_column($themes['other']['answered'], 'value'),
        );

        // الميزانية مكتوبة في ملف المشروع دون أداة، ومع ذلك تصل إلى الدفتر.
        $this->assertContains(
            '9,000',
            array_column($themes['budget_goals']['answered'], 'value'),
        );

        $offerEntry = collect($themes['offer']['answered'])->firstWhere('value', 'توصيل خلال ساعتين');
        $this->assertSame('offer-builder', $offerEntry['source']);
        $this->assertNotNull($offerEntry['answered_at']);
        $this->assertGreaterThan(0, $ledger['coverage']['answered']);
    }

    #[Test]
    public function a_published_report_without_a_numeric_score_is_still_included_and_labelled(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع بلا درجة']);

        $this->reportFor($project, $user, 'marketing-score', 61);
        $this->reportFor($project, $user, 'brand-clarity', 70);
        $this->reportFor($project, $user, 'audience-map', 64);
        $descriptive = $this->reportFor($project, $user, 'competitor-lens', null);

        $readiness = app(AgencyReportService::class)->readiness($project);
        $this->assertTrue($readiness['can_generate']);

        $report = app(AgencyReportService::class)->generate($project, $user);
        $tools = collect($report->snapshot['tools'])->keyBy('key');

        $this->assertContains($descriptive->id, $report->source_report_ids);
        $this->assertFalse($tools['competitor-lens']['scored']);
        $this->assertNotNull($tools['competitor-lens']['score_note']);
        $this->assertTrue($tools['marketing-score']['scored']);
    }

    #[Test]
    public function the_three_horizons_are_never_left_empty(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع أولوية واحدة']);

        // أولوية واحدة فقط في كامل المشروع — الحالة التي كانت تُفرغ خانة 90 يومًا.
        $this->reportFor($project, $user, 'marketing-score', 50, withRecommendation: true);
        $this->reportFor($project, $user, 'brand-clarity', 55, withRecommendation: false);
        $this->reportFor($project, $user, 'audience-map', 52, withRecommendation: false);

        $snapshot = app(AgencyReportService::class)->generate($project, $user)->snapshot;

        $this->assertCount(1, $snapshot['priorities']);

        foreach (['30_days', '60_days', '90_days'] as $horizon) {
            $this->assertNotEmpty(
                $snapshot['plan'][$horizon],
                "الأفق {$horizon} وصل فارغًا إلى مستند يُسلَّم لوكالة.",
            );
        }
    }

    #[Test]
    public function the_printed_document_carries_competitors_kpis_and_the_ledger(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);

        ProjectCompetitor::create([
            'project_id' => $project->id,
            'name' => 'منافس مؤكد',
            'url' => 'https://rival.test',
            'tier' => 'local',
            'status' => 'confirmed',
            'source' => 'user',
        ]);

        Kpi::create([
            'project_id' => $project->id,
            'name' => 'معدل التحويل',
            'unit' => '%',
            'baseline' => 1.2,
            'target' => 3.0,
            'frequency' => 'monthly',
        ]);

        $this->answer($project, 'best_customer', 'عميل الشريحة الأولى', 'audience-map');

        $report = app(AgencyReportService::class)->generate($project->fresh(), $user);
        $html = view('agency-reports.pdf', [
            'agencyReport' => $report,
            'snapshot' => $report->snapshot,
            'brand' => config('brand'),
        ])->render();

        // الحقائق التي تحتاجها الوكالة تصل إليها، أما تفاصيل المنهجية الداخلية فتبقى للمالك.
        $this->assertStringContainsString('منافس مؤكد', $html);
        $this->assertStringContainsString('معدل التحويل', $html);
        $this->assertStringContainsString('عميل الشريحة الأولى', $html);
        $this->assertStringNotContainsString('المنهجية والمصادر', $html);
        $this->assertStringNotContainsString('الملخص التنفيذي', $html);
        $this->assertStringNotContainsString('ملحق أ — الأدلة المرفوعة', $html);
        $this->assertStringNotContainsString('ملحق ب — أصول جاهزة للنشر', $html);
    }

    #[Test]
    public function the_agency_brief_is_complete_for_the_agency_without_owner_only_evidence(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);

        ProjectCompetitor::create([
            'project_id' => $project->id,
            'name' => 'منافس داخلي جدًا',
            'tier' => 'local',
            'status' => 'confirmed',
            'source' => 'user',
        ]);

        $report = app(AgencyReportService::class)->generate($project->fresh(), $user, [
            'competitors' => 'private',
            'evidence' => 'private',
        ]);

        $html = view('agency-reports.pdf', [
            'agencyReport' => $report,
            'snapshot' => $report->snapshot,
            'brand' => config('brand'),
        ])->render();

        $this->assertStringContainsString('منافس داخلي جدًا', $html);
        $this->assertStringNotContainsString('إجابة موثقة من المستخدم', $html);
    }

    #[Test]
    public function the_printed_document_never_shows_raw_english_values_or_field_keys(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $project->update(['stage' => 'growth']);
        $project->profile()->update(['business_model' => 'b2c']);

        ProjectCompetitor::create([
            'project_id' => $project->id,
            'name' => 'منافس',
            'tier' => 'regional',
            'status' => 'confirmed',
            'source' => 'user',
        ]);

        // اسم المشروع وقطاعه تكتبهما المنصة نفسها في ذاكرة الإجابات عند
        // إنشاء المشروع، فتصل إلى الدفتر دون أن تمر بأي أداة.
        $this->assertDatabaseHas('project_answers', [
            'project_id' => $project->id,
            'field_key' => 'industry',
        ]);

        $report = app(AgencyReportService::class)->generate($project->fresh(), $user);
        $html = view('agency-reports.pdf', [
            'agencyReport' => $report,
            'snapshot' => $report->snapshot,
            'brand' => config('brand'),
        ])->render();

        // القيم المعدودة تُطبع بالعربية لا بمفاتيحها.
        $this->assertStringContainsString('زيادة المبيعات مباشرة', $html);
        $this->assertStringContainsString('إقليمي', $html);
        $this->assertStringNotContainsString('الهدف:</b> sales', $html);
        $this->assertStringNotContainsString('الخطورة: high', $html);
        $this->assertStringNotContainsString('الأثر: high', $html);
        $this->assertStringNotContainsString('الجهد: low', $html);

        // سمات المشروع لها تسمية عربية ولا تظهر بمفتاحها الخام كبند.
        $labels = collect($report->snapshot['ledger']['themes'])
            ->flatMap(fn (array $theme) => array_column($theme['answered'], 'label'));

        $this->assertContains('اسم المشروع', $labels);
        $this->assertContains('القطاع', $labels);
        $this->assertNotContains('industry', $labels);
        $this->assertNotContains('name', $labels);
    }

    private function readyProject(User $user): Project
    {
        $project = app(ProjectService::class)->create($user, [
            'name' => 'مشروع كامل',
            'industry' => 'التجارة الإلكترونية',
        ]);
        $project->profile()->updateOrCreate([], [
            'description' => 'متجر منتجات محلية.',
            'monthly_budget' => 12000,
            'primary_goal' => 'sales',
            'value_proposition' => 'توصيل سريع.',
            'geography' => 'الرياض',
        ]);

        $this->reportFor($project, $user, 'marketing-score', 62);
        $this->reportFor($project, $user, 'brand-clarity', 71);
        $this->reportFor($project, $user, 'audience-map', 58);

        return $project;
    }

    private function answer(Project $project, string $key, mixed $value, ?string $tool): void
    {
        ProjectAnswer::create([
            'project_id' => $project->id,
            'field_key' => $key,
            'value_json' => ['value' => $value],
            'source_tool_key' => $tool,
        ]);
    }

    private function reportFor(
        Project $project,
        User $user,
        string $toolKey,
        ?int $score,
        bool $withRecommendation = true,
    ): Report {
        $tool = Tool::where('key', $toolKey)->firstOrFail();
        $run = $project->runs()->create([
            'tool_version_id' => $tool->current_version_id,
            'user_id' => $user->id,
            'status' => ToolRun::STATUS_COMPLETED,
            'base_score' => $score,
        ]);

        $report = Report::create([
            'tool_run_id' => $run->id,
            'project_id' => $project->id,
            'title' => "تقرير {$tool->title}",
            'status' => 'published',
            'score' => $score,
            'score_band' => $score === null ? null : Report::bandFor($score),
            'summary' => "ملخص {$tool->title}.",
            'assumptions' => [],
        ]);

        if (! $withRecommendation) {
            return $report;
        }

        $finding = Finding::create([
            'report_id' => $report->id,
            'category' => 'القياس',
            'title' => "نتيجة {$toolKey}",
            'description' => 'وصف النتيجة.',
            'severity' => 'high',
            'evidence' => 'إجابة موثقة من المستخدم',
            'confidence' => 80,
            'is_assumption' => false,
            'sort_order' => 0,
        ]);

        Recommendation::create([
            'finding_id' => $finding->id,
            'report_id' => $report->id,
            'title' => "أولوية {$toolKey}",
            'description' => 'خطوة تنفيذية قابلة للقياس.',
            'impact' => 'high',
            'effort' => 'low',
            'priority' => 90,
            'kpi_hint' => 'معدل التحويل',
        ]);

        return $report;
    }
}
