<?php

namespace Tests\Feature;

use App\Models\Finding;
use App\Models\Recommendation;
use App\Models\Report;
use App\Models\ReportWatcher;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use App\Modules\Reporting\ReportCharts;
use App\Services\Projects\ProjectService;
use App\Services\Tools\ToolRunService;
use App\Support\Presentation\ReportPresenter;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * الملف المطبوع يحمل كل ما تعرضه صفحة التقرير في الويب والتطبيق.
 *
 * نتحقق من قالب الطباعة نفسه (HTML قبل التحويل) لأن الـPDF ثنائي مضغوط:
 * لو حُذف قسم من الشاشة أو أُضيف بلا نظير في الطباعة، يسقط هذا الاختبار.
 */
class ReportPdfParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    #[Test]
    public function the_printed_report_carries_everything_the_screen_shows(): void
    {
        $report = $this->report();

        $html = view('reports.pdf', [
            'report' => app(ReportPresenter::class)->full($report),
            'charts' => app(ReportCharts::class)->build($report),
            'comparison' => app(ReportPresenter::class)->comparison($report, app(ReportPresenter::class)->previousFor($report)),
            'watcher' => $report->watcher,
            'suggestion' => null,
            'brand' => config('brand'),
            'generatedAt' => now(),
        ])->render();

        foreach ([
            // الرأس: الدرجة والنطاق والمقارنة الزمنية وتوثيق المراجعة اليدوية.
            'تقرير المطابقة',
            'مستقر',
            'منذ آخر مرة',
            'ليست نتيجة آلة',
            // الخلاصة وعدّاد الدليل مقابل الاجتهاد.
            'الخلاصة',
            'نتيجة مبنية على ما كتبته',
            // الرسوم البيانية.
            'المؤشرات في لمحة',
            'النتائج حسب درجة الخطورة',
            'ما يستند إلى كلامك وما يحتاج تأكيدك',
            'خريطة التوصيات: الأثر مقابل الجهد',
            // الخطوة التالية والتقرير الحي.
            'الخطوة التالية',
            'تقريرك الحي رصد تغييرًا',
            'تغيّر ملف مشروعك',
            // النتائج والتوصيات وحالة التحويل إلى مهمة.
            'ما وجدناه لك، وكيف تعالجه',
            'القياس ناقص',
            'الدليل:',
            'ثبّت أداة تحليلات',
            'أصبحت مهمة',
            // الافتراضات وتفاصيل التحليل بأنواع أقسامها.
            'أشياء خمّناها، ونحتاج تأكيدك عليها',
            'يحتاج تأكيدًا بمقابلات',
            'تفاصيل التحليل',
            'كيف حُسبت درجتك',
            'وضوح الجمهور',
            'منافسوك',
        ] as $needle) {
            $this->assertStringContainsString($needle, $html, "الملف المطبوع لا يحمل: {$needle}");
        }

        foreach ([
            'class="print-report"',
            'class="summary-box print-section"',
            'class="section-box print-section print-section--long"',
            'class="matrix print-table"',
            'break-inside: avoid',
            'table-layout: fixed',
            'overflow-wrap: anywhere',
        ] as $layoutContract) {
            $this->assertStringContainsString($layoutContract, $html, "عقد الطباعة يفتقد: {$layoutContract}");
        }
    }

    #[Test]
    public function the_printed_report_uses_the_same_font_file_as_the_website(): void
    {
        // خط الموقع معرّف في partials/font.blade.php ويشير إلى هذا الملف نفسه.
        $webFont = file_get_contents(resource_path('views/partials/font.blade.php'));
        $this->assertStringContainsString('Hacen-Tunisia.ttf', $webFont);

        $this->assertFileExists(public_path('assets/fonts/Hacen-Tunisia.ttf'));

        // المولّد يسجّل الملف نفسه بلا تبديل تلقائي إلى خط mPDF المدمج.
        $generator = file_get_contents(app_path('Modules/Reporting/ReportPdfGenerator.php'));
        $this->assertStringContainsString("'R' => 'Hacen-Tunisia.ttf'", $generator);
        $this->assertStringContainsString("'autoLangToFont' => false", $generator);
        $this->assertStringContainsString("'default_font' => 'hacentunisia'", $generator);
    }

    private function report(): Report
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع المطابقة']);
        $tool = Tool::where('key', 'marketing-score')->firstOrFail();

        $olderRun = app(ToolRunService::class)->start($project, $tool, $user);
        $olderRun->forceFill(['status' => ToolRun::STATUS_COMPLETED, 'base_score' => 40])->save();
        Report::create([
            'tool_run_id' => $olderRun->id,
            'project_id' => $project->id,
            'title' => 'تقرير سابق',
            'status' => 'published',
            'score' => 40,
            'score_band' => 'يحتاج ترتيبًا',
            'summary' => 'ملخص سابق.',
        ])->forceFill(['created_at' => now()->subDays(12)])->save();

        $run = app(ToolRunService::class)->start($project, $tool, $user);
        $run->forceFill(['status' => ToolRun::STATUS_COMPLETED, 'base_score' => 62])->save();

        $report = Report::create([
            'tool_run_id' => $run->id,
            'project_id' => $project->id,
            'title' => 'تقرير المطابقة',
            'status' => 'published',
            'score' => 62,
            'score_band' => 'مستقر',
            'summary' => 'ملخص التقرير.',
            'next_step' => ['title' => 'ابدأ هنا', 'description' => 'خطوة أولى واضحة.'],
            'assumptions' => ['يحتاج تأكيدًا بمقابلات مع عملاء حقيقيين.'],
        ]);

        $report->forceFill(['review_mode' => 'manual', 'reviewed_at' => now()])->save();

        ReportWatcher::create([
            'report_id' => $report->id,
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => ReportWatcher::STATUS_ACTIVE,
            'baseline_fingerprint' => 'fingerprint',
            'changes' => [['text' => 'تغيّر ملف مشروعك منذ إصدار التقرير.']],
            'last_checked_at' => now(),
        ]);

        $report->sections()->createMany([
            [
                'key' => 'score',
                'title' => 'كيف حُسبت درجتك',
                'sort_order' => 0,
                'content_json' => [
                    'method' => 'مجموع أوزان المحاور.',
                    'breakdown' => [['label' => 'وضوح الجمهور', 'points' => 8, 'weight' => 20]],
                ],
            ],
            [
                'key' => 'competitors',
                'title' => 'منافسوك',
                'sort_order' => 1,
                'content_json' => ['intro' => 'من ينافسك فعلًا.', 'look_for' => ['كم إعلانًا يشغّلون.']],
            ],
        ]);

        $finding = Finding::create([
            'report_id' => $report->id,
            'category' => 'القياس',
            'title' => 'القياس ناقص',
            'description' => 'لا يوجد تتبع للتحويلات.',
            'severity' => 'high',
            'evidence' => 'إجابة المستخدم',
            'confidence' => 80,
            'is_assumption' => false,
            'sort_order' => 0,
        ]);

        $recommendation = Recommendation::create([
            'finding_id' => $finding->id,
            'report_id' => $report->id,
            'title' => 'ثبّت أداة تحليلات',
            'description' => 'أضف قياسًا خلال أسبوع.',
            'impact' => 'high',
            'effort' => 'low',
            'priority' => 80,
        ]);

        app(ToolRunService::class)->convertRecommendation($recommendation, $user);

        return $report->fresh();
    }
}
