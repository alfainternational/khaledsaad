<?php

namespace Tests\Feature;

use App\Models\Finding;
use App\Models\Recommendation;
use App\Models\Report;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use App\Services\Projects\ProjectService;
use App\Services\Reports\ReportPdfGenerator;
use App\Services\Tools\ToolRunService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * مؤقت: يولّد عينة PDF غنية للمعاينة البصرية ثم يُحذف.
 */
class TmpPdfSampleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    #[Test]
    public function it_builds_a_rich_sample_pdf(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'جيب لي']);
        $tool = Tool::where('key', 'marketing-score')->firstOrFail();

        // تقريران سابقان لإظهار مخطط تطور الدرجة.
        foreach ([['score' => 28, 'days' => 40], ['score' => 35, 'days' => 20]] as $old) {
            $oldRun = app(ToolRunService::class)->start($project, $tool, $user);
            $oldRun->forceFill(['status' => ToolRun::STATUS_COMPLETED, 'base_score' => $old['score']])->save();
            Report::create([
                'tool_run_id' => $oldRun->id,
                'project_id' => $project->id,
                'title' => 'تقرير سابق',
                'status' => 'published',
                'score' => $old['score'],
                'score_band' => 'يحتاج ترتيبًا',
                'summary' => 'ملخص سابق.',
            ])->forceFill(['created_at' => now()->subDays($old['days'])])->save();
        }

        $run = app(ToolRunService::class)->start($project, $tool, $user);
        $run->forceFill(['status' => ToolRun::STATUS_COMPLETED, 'base_score' => 41])->save();

        $report = Report::create([
            'tool_run_id' => $run->id,
            'project_id' => $project->id,
            'title' => 'أين مشروعك الآن؟ — جيب لي',
            'status' => 'published',
            'score' => 41,
            'score_band' => 'يحتاج ترتيبًا',
            'summary' => 'مشروعك ينمو أعمالًا قابلة للقياس، لكن غياب أدوات التتبع وصفحة الهبوط يجعل أي جهد تسويقي بلا رؤية. الهدف الآن تحويل الزوار إلى عملاء محتملين بقياس دقيق.',
            'next_step' => [
                'title' => 'فعّل القياس أولًا',
                'description' => 'قم بإنشاء حساب Google Analytics وأضف شفرة التتبع إلى صفحاتك الرئيسية، ثم راجع البيانات بعد 24 ساعة.',
            ],
            'assumptions' => [
                'ربما لم يختبر بعد عبر بحث منافسين حقيقي في السوقين السوداني والخليجي.',
                'أصحاب المشاريع الصغيرة والمتوسطة هم الشريحة الأعلى قيمة — يحتاج تحققًا بمقابلات.',
            ],
        ]);

        $rows = [
            ['غياب القدرة على قياس الوعي وتحويل الزوار', 'critical', false, 'إجابات المستخدم: tracking_maturity = none', [
                ['إنشاء صفحة هبوط بسيطة خلال أسبوعين', 'high', 'low'],
                ['تفعيل Google Analytics على الموقع الرئيسي', 'high', 'low'],
            ]],
            ['تحديد الجمهور المستهدف لا يزال تقريبيًا', 'high', true, "إجابة المستخدم: 'rough'", [
                ['إجراء 5 مقابلات هاتفية مع عملاء محتملين', 'high', 'medium'],
            ]],
            ['تشتت القنوات التسويقية دون تمويل أو قياس', 'high', false, 'active_channels = [seo, social, whatsapp], monthly_budget = 0', [
                ['التركيز على قناة واحدة لمدة 6 أسابيع', 'medium', 'low'],
            ]],
            ['عرض القيمة الفريد مدعوم بأدلة سوقية غير كافية', 'medium', true, 'لا يوجد نص عرض قيمة من المستخدم', [
                ['تحليل سريع لأبرز 3 منافسين', 'medium', 'medium'],
            ]],
            ['غياب استراتيجية الاحتفاظ بالعملاء بعد الشراء', 'medium', false, 'retention_motion = none', [
                ['إعداد رسالة بريد ترحيبية تلقائية', 'medium', 'low'],
            ]],
            ['الاعتماد على قناة تواصل واحدة هش', 'low', true, 'ملاحظة تحليلية', [
                ['توثيق جهات الاتصال خارج المنصة', 'low', 'low'],
            ]],
        ];

        foreach ($rows as $i => [$title, $severity, $assumption, $evidence, $recs]) {
            $finding = Finding::create([
                'report_id' => $report->id,
                'category' => 'التسويق',
                'title' => $title,
                'description' => 'تفصيل عملي يشرح المشكلة بلغة صاحب المشروع وسبب أهميتها الآن، وما الذي يخسره كل أسبوع تأخير.',
                'severity' => $severity,
                'evidence' => $evidence,
                'confidence' => 80,
                'is_assumption' => $assumption,
                'sort_order' => $i,
            ]);

            foreach ($recs as [$recTitle, $impact, $effort]) {
                Recommendation::create([
                    'finding_id' => $finding->id,
                    'report_id' => $report->id,
                    'title' => $recTitle,
                    'description' => 'خطوة تنفيذية واضحة بمدة وأداة مجانية مقترحة.',
                    'impact' => $impact,
                    'effort' => $effort,
                    'priority' => 70,
                ]);
            }
        }

        $path = app(ReportPdfGenerator::class)->generate($report->fresh());

        copy(
            storage_path('app/private/'.$path),
            'C:/Users/lenovo/AppData/Local/Temp/claude/D--xampp-htdocs-khaledsaad/d2bfb76e-c446-4adb-b5ce-c8a8c89fca4a/scratchpad/sample-report.pdf'
        );

        $this->assertTrue(true);
    }
}
