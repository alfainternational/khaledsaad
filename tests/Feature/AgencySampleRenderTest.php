<?php

namespace Tests\Feature;

use App\Models\Finding;
use App\Models\Kpi;
use App\Models\KpiEntry;
use App\Models\Project;
use App\Models\ProjectAnswer;
use App\Models\ProjectAudience;
use App\Models\ProjectCompetitor;
use App\Models\Recommendation;
use App\Models\Report;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use App\Services\Projects\ProjectService;
use App\Services\Reports\AgencyReportPdfGenerator;
use App\Services\Reports\AgencyReportService;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * توليد عينة قابلة للفحص البصري من مشروع مكتمل البيانات.
 *
 * خارج المجموعة الافتراضية لأنها تكتب ملفًا على القرص: تُشغَّل عند الحاجة
 * بـ --group=sample، ولا تبطئ كل تشغيل للاختبارات.
 */
#[Group('sample')]
class AgencySampleRenderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_writes_a_full_sample_document_for_visual_review(): void
    {
        $this->seed(ToolCatalogSeeder::class);

        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, [
            'name' => 'متجر رفوف المنزل',
            'industry' => 'التجارة الإلكترونية',
        ]);
        $project->profile()->updateOrCreate([], [
            'business_model' => 'b2c',
            'description' => 'متجر إلكتروني لأثاث التخزين المنزلي يبيع داخل الرياض وجدة.',
            'geography' => 'السعودية — الرياض وجدة',
            'website' => 'https://shelves.test',
            'monthly_budget' => 18000,
            'primary_goal' => 'sales',
            'value_proposition' => 'رفوف تُركَّب في عشر دقائق دون فني، مع ضمان سنتين.',
        ]);

        foreach ([
            'what_you_sell' => 'رفوف وخزائن تخزين جاهزة التركيب',
            'best_customer' => 'أسر شابة انتقلت لسكن جديد خلال آخر ستة أشهر',
            'customer_problem' => 'المساحة ضيقة والتركيب يحتاج فنيًا ينتظرونه أيامًا',
            'main_objection' => 'يشكّون أن الجودة أقل من المتاجر الكبيرة',
            'average_order_value' => '640',
            'known_cac' => '95',
            'monthly_visitors' => '14000',
            'monthly_customers' => '210',
            'tracking_maturity' => 'basic',
            'active_channels' => 'إنستغرام، إعلانات جوجل، البحث العضوي',
            'best_channel_today' => 'إنستغرام',
            'checkout_steps' => 'أربع خطوات مع تسجيل إجباري',
            'response_time' => 'خلال ساعتين في أيام العمل',
            'repeat_rate' => '18',
            'brand_tone' => 'عملي ومباشر بلا مبالغة',
            'who_owns_assets' => 'الحسابات باسم المالك',
        ] as $key => $value) {
            ProjectAnswer::create([
                'project_id' => $project->id,
                'field_key' => $key,
                'value_json' => ['value' => $value],
                'source_tool_key' => 'marketing-score',
            ]);
        }

        // موجز تكليف مكتمل حتى تختبر العينة المسار المالي لا الفراغ.
        app(AgencyReportService::class)->saveBrief($project, [
            'services' => ['ads', 'content', 'analytics'],
            'budget_includes_agency_fee' => 'no',
            'budget_currency' => 'SAR',
            'success_metric' => '80 طلبًا شهريًا بتكلفة استحواذ أقل من 110 ريال',
            'timeframe_months' => '6',
        ]);

        ProjectAudience::create([
            'project_id' => $project->id,
            'name' => 'المنتقلون حديثًا',
            'pains' => 'مساحة ضيقة وميزانية تأثيث محدودة بعد الانتقال',
            'gains' => 'ترتيب البيت خلال أسبوع دون انتظار فني',
            'behaviors' => 'يبحثون بالصور على إنستغرام ويقارنون قبل الشراء بأسبوعين',
        ]);

        foreach ([
            ['ايكيا السعودية', 'https://ikea.test', 'regional'],
            ['متجر ترتيب', 'https://tarteeb.test', 'local'],
        ] as [$name, $url, $tier]) {
            ProjectCompetitor::create([
                'project_id' => $project->id,
                'name' => $name,
                'url' => $url,
                'tier' => $tier,
                'status' => 'confirmed',
                'source' => 'user',
            ]);
        }

        $kpi = Kpi::create([
            'project_id' => $project->id,
            'name' => 'معدل التحويل من الزيارة إلى الطلب',
            'unit' => '%',
            'baseline' => 1.5,
            'target' => 2.8,
            'frequency' => 'monthly',
        ]);
        KpiEntry::create([
            'kpi_id' => $kpi->id,
            'value' => 1.7,
            'recorded_at' => now()->subDays(5),
            'source' => 'manual',
        ]);

        $this->reportFor($project, $user, 'marketing-score', 58, 'critical');
        $this->reportFor($project, $user, 'brand-clarity', 71, 'medium');
        $this->reportFor($project, $user, 'audience-map', 64, 'high');
        $this->reportFor($project, $user, 'funnel-audit', 49, 'high');
        $this->reportFor($project, $user, 'competitor-lens', null, 'medium');

        $report = app(AgencyReportService::class)->generate($project->fresh(), $user);
        $path = app(AgencyReportPdfGenerator::class)->ensure($report);
        $bytes = Storage::disk('local')->get($path);

        $target = base_path('output/pdf/agency-report-sample.pdf');

        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0775, true);
        }

        file_put_contents($target, $bytes);

        $this->assertGreaterThan(20000, strlen($bytes));
        $this->assertFileExists($target);
    }

    private function reportFor(
        Project $project,
        User $user,
        string $toolKey,
        ?int $score,
        string $severity,
    ): void {
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
            'summary' => "خلاصة {$tool->title}: الوضع الحالي موثّق بإجابات صاحب المشروع، والفجوات محددة بالاسم.",
            'assumptions' => ['نسبة التكرار مُقدّرة من الذاكرة لا من تقرير مبيعات.'],
        ]);

        $report->sections()->create([
            'key' => 'analysis',
            'title' => 'التحليل',
            'sort_order' => 0,
            'content_json' => [
                'headline' => 'أهم ما ظهر في هذا المحور',
                'points' => [['text' => 'نقطة تحليلية', 'evidence' => 'إجابة صاحب المشروع']],
            ],
        ]);

        $finding = Finding::create([
            'report_id' => $report->id,
            'category' => 'القياس',
            'title' => "فجوة {$tool->title}",
            'description' => 'الفجوة موصوفة بما يكفي لبناء خطة عليها دون إعادة سؤال.',
            'severity' => $severity,
            'evidence' => 'مأخوذة من إجابات المعالج المحفوظة.',
            'confidence' => 80,
            'is_assumption' => false,
            'sort_order' => 0,
        ]);

        Recommendation::create([
            'finding_id' => $finding->id,
            'report_id' => $report->id,
            'title' => "خطوة {$tool->title}",
            'description' => 'إجراء محدد قابل للقياس خلال أسبوعين.',
            'impact' => 'high',
            'effort' => 'low',
            'priority' => 85,
            'kpi_hint' => 'معدل التحويل',
        ]);
    }
}
