<?php

namespace Tests\Feature;

use App\Models\AgencyReport;
use App\Models\BenchmarkSnapshot;
use App\Models\Finding;
use App\Models\Project;
use App\Models\ProjectAnswer;
use App\Models\Recommendation;
use App\Models\Report;
use App\Models\Task;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use App\Services\Projects\ProjectService;
use App\Services\Reports\AgencyReportService;
use App\Services\Reports\AgencyReportSharing;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * الأقسام التي تحوّل المستند من «مفهوم» إلى «قابل للتسعير والبدء».
 */
class AgencyOperationalFileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ToolCatalogSeeder::class);
    }

    #[Test]
    public function every_number_carries_its_confidence_level_and_absent_ones_are_declared_not_dropped(): void
    {
        [$user, $project] = $this->project();
        $this->answers($project, [
            'tracking_maturity' => 'basic',
            'monthly_visitors' => '14000',
            'monthly_customers' => '210',
            'average_order_value' => '640',
        ]);

        $numbers = app(AgencyReportService::class)
            ->generate($project->fresh(), $user)
            ->snapshot['numbers'];

        $rows = collect($numbers['rows'])->keyBy('key');

        // تتبع جزئي ⇒ الأرقام السلوكية مقيسة جزئيًا لا مقيسة بالكامل.
        $this->assertSame('partial', $rows['monthly_visitors']['confidence']);
        // رقم من دفاتر المشروع لا علاقة له بنضج التتبع.
        $this->assertSame('stated', $rows['average_order_value']['confidence']);
        // الغائب يبقى صفًا معلنًا لا يُحذف من الجدول.
        $this->assertSame('unknown', $rows['repeat_rate']['confidence']);
        $this->assertNull($rows['repeat_rate']['value']);

        // نسبة التحويل تُشتق فقط حين يتوفر طرفاها.
        $this->assertSame('1.50', $rows['conversion_rate']['value']);
    }

    #[Test]
    public function a_derived_rate_is_absent_when_either_side_of_it_is_unknown(): void
    {
        [$user, $project] = $this->project();
        $this->answers($project, ['monthly_visitors' => '9000']);

        $numbers = app(AgencyReportService::class)
            ->generate($project->fresh(), $user)
            ->snapshot['numbers'];

        $this->assertNotContains('conversion_rate', array_column($numbers['rows'], 'key'));
    }

    #[Test]
    public function a_market_reference_is_attached_with_its_source_and_date(): void
    {
        [$user, $project] = $this->project();
        $this->answers($project, ['known_cac' => '95', 'tracking_maturity' => 'full']);

        BenchmarkSnapshot::create([
            'metric' => 'cost_per_customer',
            'industry' => $project->industry,
            'geography' => 'SA',
            'business_model' => 'b2c',
            'value_low' => 70,
            'value_high' => 130,
            'unit' => 'ريال',
            'source_name' => 'مرجع السوق',
            'source_url' => 'https://source.test',
            'fetched_at' => now()->subDays(3),
        ]);

        $rows = collect(app(AgencyReportService::class)
            ->generate($project->fresh(), $user)
            ->snapshot['numbers']['rows'])->keyBy('key');

        $this->assertSame('70 – 130', $rows['known_cac']['benchmark']['range']);
        $this->assertSame('مرجع السوق', $rows['known_cac']['benchmark']['source']);
        $this->assertNotNull($rows['known_cac']['benchmark']['fetched_at']);
        $this->assertSame('measured', $rows['known_cac']['confidence']);
    }

    #[Test]
    public function the_asset_inventory_separates_declared_from_unknown_access(): void
    {
        [$user, $project] = $this->project();
        $this->answers($project, [
            'website' => 'https://shop.test',
            'who_owns_assets' => 'الحسابات باسم المالك',
        ]);

        $assets = app(AgencyReportService::class)
            ->generate($project->fresh(), $user)
            ->snapshot['assets'];

        $rows = collect($assets['rows'])->keyBy('key');

        $this->assertSame('declared', $rows['website']['status']);
        $this->assertSame('unknown', $rows['search_console']['status']);
        $this->assertStringContainsString('shop.test', $rows['website']['detail']);
        $this->assertContains('أدوات مشرفي المواقع', $assets['unknown']);
        $this->assertStringContainsString('باسم المالك', $assets['ownership_note']);
        $this->assertGreaterThan(0, $assets['readiness_percent']);
    }

    #[Test]
    public function the_behaviour_log_reports_execution_and_trend(): void
    {
        [$user, $project] = $this->project();

        Task::create([
            'project_id' => $project->id,
            'title' => 'مهمة منجزة',
            'status' => Task::STATUS_DONE,
            'impact' => 'high',
            'effort' => 'low',
        ]);
        Task::create([
            'project_id' => $project->id,
            'title' => 'مهمة مفتوحة',
            'status' => Task::STATUS_TODO,
            'impact' => 'medium',
            'effort' => 'medium',
        ]);

        // قياس ثانٍ لدرجة الجاهزية حتى يصبح للاتجاه معنى.
        $this->reportFor($project, $user, 'marketing-score', 74);

        $behaviour = app(AgencyReportService::class)
            ->generate($project->fresh(), $user)
            ->snapshot['behaviour'];

        $this->assertSame(2, $behaviour['tasks']['total']);
        $this->assertSame(1, $behaviour['tasks']['done']);
        $this->assertSame(50, $behaviour['tasks']['completion_percent']);
        $this->assertSame('up', $behaviour['trend']['direction']);
        $this->assertSame(74, $behaviour['trend']['to']);
    }

    #[Test]
    public function the_decision_card_never_contradicts_the_document_it_summarises(): void
    {
        [$user, $project] = $this->project();
        $this->answers($project, ['tracking_maturity' => 'full', 'monthly_visitors' => '5000']);

        $snapshot = app(AgencyReportService::class)->generate($project->fresh(), $user)->snapshot;
        $card = $snapshot['decision_card'];

        $this->assertSame($snapshot['executive']['position'], $card['readiness']['score']);
        $this->assertSame($snapshot['ledger']['coverage']['percent'], $card['coverage']['knowledge_percent']);
        $this->assertSame($snapshot['assets']['readiness_percent'], $card['coverage']['assets_percent']);
        $this->assertSame($snapshot['numbers']['summary']['total'], $card['coverage']['numbers_total']);
        $this->assertSame($snapshot['executive']['problems'][0]['title'], $card['signals']['risk']);
        $this->assertNotNull($card['signals']['unknown']);
    }

    #[Test]
    public function a_fact_written_only_on_the_profile_reaches_the_asset_inventory_too(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع بملف فقط']);

        // كُتب في ملف المشروع مباشرة دون المرور بأداة — كما يحدث عند تعديله يدويًا.
        $project->profile()->updateOrCreate([], ['website' => 'https://direct.test']);

        foreach (['marketing-score' => 60, 'brand-clarity' => 65, 'audience-map' => 62] as $key => $score) {
            $this->reportFor($project, $user, $key, $score);
        }

        $snapshot = app(AgencyReportService::class)->generate($project->fresh(), $user)->snapshot;

        $ledgerValues = collect($snapshot['ledger']['themes'])
            ->flatMap(fn (array $theme) => array_column($theme['answered'], 'value'));
        $website = collect($snapshot['assets']['rows'])->firstWhere('key', 'website');

        // القسمان يقرآن المصدر نفسه؛ لا يجوز أن يعرفه أحدهما ويجهله الآخر.
        $this->assertContains('https://direct.test', $ledgerValues);
        $this->assertSame('declared', $website['status']);
    }

    #[Test]
    public function a_withheld_number_is_never_confused_with_an_uncomputed_one(): void
    {
        [$user, $project] = $this->project();

        // ميزانية معلنة لكن لم يُحسم هل تشمل الأتعاب ⇒ لا يمكن حساب الوسائط.
        $uncomputed = app(AgencyReportService::class)->generate($project->fresh(), $user);
        $printed = $this->print($uncomputed);

        $this->assertStringContainsString('لم يُحسم بعد', $printed);
        $this->assertStringNotContainsString('غير معروض في هذه النسخة بطلب صاحب المشروع', $printed);

        // نفس المشروع بحجب صريح ⇒ السبب يتغير إلى قرار صاحب المشروع.
        $withheld = app(AgencyReportService::class)
            ->generate($project->fresh(), $user, ['budget' => 'private']);

        $this->assertStringContainsString(
            'غير معروض في هذه النسخة بطلب صاحب المشروع',
            $this->print($withheld),
        );
    }

    private function print(AgencyReport $report): string
    {
        return view('agency-reports.pdf', [
            'agencyReport' => $report,
            'snapshot' => $report->snapshot,
            'brand' => config('brand'),
        ])->render();
    }

    #[Test]
    public function the_owner_only_guide_never_reaches_anything_handed_to_an_agency(): void
    {
        [$user, $project] = $this->project();
        $report = app(AgencyReportService::class)->generate($project->fresh(), $user);

        // موجود في اللقطة لصاحب المشروع.
        $this->assertNotEmpty($report->snapshot['owner_guide']);

        $printed = view('agency-reports.pdf', [
            'agencyReport' => $report,
            'snapshot' => $report->snapshot,
            'brand' => config('brand'),
        ])->render();

        $shared = view('agency-reports.shared', [
            'agencyReport' => $report,
            'snapshot' => $report->snapshot,
            'shareToken' => 'token',
        ])->render();

        foreach (['القسم التالي لا تراه الوكالة', 'أوراقك في التفاوض', 'علامات الإنذار'] as $ownerOnly) {
            $this->assertStringNotContainsString($ownerOnly, $printed);
            $this->assertStringNotContainsString($ownerOnly, $shared);
        }

        // وحتى نسخة البيانات الآلية تُجرَّد منه حتمًا لا اعتمادًا على القالب.
        $this->assertArrayNotHasKey('owner_guide', app(AgencyReportSharing::class)->dataFile($report)['snapshot']);
    }

    #[Test]
    public function the_data_companion_is_reachable_by_the_owner_and_through_a_live_link_only(): void
    {
        [$user, $project] = $this->project();
        $report = app(AgencyReportService::class)->generate($project->fresh(), $user);

        $this->actingAs($user)
            ->get(route('app.agency-reports.data', $report))
            ->assertOk()
            ->assertJsonPath('document.version', 1)
            ->assertJsonStructure(['snapshot' => ['decision_card', 'numbers', 'assets', 'behaviour']]);

        app(AgencyReportSharing::class)->share($report, 30);
        $token = $report->fresh()->share_token;

        $this->get(route('shared.agency-report.data', $token))->assertOk();

        app(AgencyReportSharing::class)->revoke($report->fresh());
        $this->get(route('shared.agency-report.data', $token))->assertNotFound();
    }

    /**
     * @param  array<string, string>  $answers
     */
    private function answers(Project $project, array $answers): void
    {
        foreach ($answers as $key => $value) {
            ProjectAnswer::updateOrCreate(
                ['project_id' => $project->id, 'field_key' => $key],
                ['value_json' => ['value' => $value], 'source_tool_key' => 'marketing-score'],
            );
        }
    }

    /**
     * @return array{0: User, 1: Project}
     */
    private function project(): array
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, [
            'name' => 'مشروع تشغيلي',
            'industry' => 'التجارة الإلكترونية',
        ]);
        $project->profile()->updateOrCreate([], [
            'monthly_budget' => 18000,
            'primary_goal' => 'sales',
            'business_model' => 'b2c',
        ]);

        foreach (['marketing-score' => 58, 'brand-clarity' => 70, 'audience-map' => 63] as $key => $score) {
            $this->reportFor($project, $user, $key, $score);
        }

        return [$user, $project];
    }

    private function reportFor(Project $project, User $user, string $toolKey, int $score): void
    {
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
            'score_band' => Report::bandFor($score),
            'summary' => "ملخص {$tool->title}.",
            'assumptions' => [],
        ]);

        $finding = Finding::create([
            'report_id' => $report->id,
            'category' => 'القياس',
            'title' => "فجوة {$toolKey}",
            'description' => 'وصف.',
            'severity' => 'high',
            'evidence' => 'إجابة موثقة.',
            'confidence' => 80,
            'is_assumption' => false,
            'sort_order' => 0,
        ]);

        Recommendation::create([
            'finding_id' => $finding->id,
            'report_id' => $report->id,
            'title' => "خطوة {$toolKey}",
            'description' => 'إجراء قابل للقياس.',
            'impact' => 'high',
            'effort' => 'low',
            'priority' => 88,
        ]);
    }
}
