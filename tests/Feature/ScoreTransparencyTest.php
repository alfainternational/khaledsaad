<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\Tool;
use App\Models\User;
use App\Services\Projects\ProjectService;
use App\Services\Tools\ManualReportService;
use App\Services\Tools\ToolRunService;
use Database\Seeders\PlanSeeder;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * الدرجة لا تُعرض كرقم مجرّد.
 *
 * «0 / 20» وحدها لا تخبر صاحب النشاط بشيء: لا بماذا أجاب، ولا لماذا صفر
 * لا نصف، ولا كم يزن البند من درجته، ولا أي بند استُبعد عنه أصلًا. وما دامت
 * الأوزان تقديرًا منهجيًّا لا معايرة (§٤.١)، فإخفاء أساسها يحوّل الفرضية إلى
 * حقيقة في عين القارئ. هذه الاختبارات تحرس ظهور الشرح كاملًا.
 */
class ScoreTransparencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PlanSeeder::class, ToolCatalogSeeder::class]);
    }

    #[Test]
    public function the_report_page_explains_every_point_it_awarded(): void
    {
        [$user, $report] = $this->reportForCompletedRun();

        $response = $this->actingAs($user)->get(route('app.reports.show', $report->id))->assertOk();

        // أساس الأوزان معلن بوسم الفرضية، لا معروض كمعايرة.
        $response->assertSee('فرضية منهجية');
        $response->assertSee('لا معايرة على بيانات حملات');

        // كل بند يحمل حصته من الدرجة ونص إجابة صاحبه.
        $response->assertSee('من درجتك');
        $response->assertSee('تعطي معامل');
        $response->assertSee('السؤال:');
        $response->assertSee('إجابتك:');

        // القاسم معلن: بدونه لا يعرف القارئ أن «10» ليست 10٪.
        $response->assertSee('مجموع أوزان البنود المنطبقة على مشروعك');

        // سُلّم الثقل معلن بدل تبرير مؤلَّف لكل رقم على حدة.
        $response->assertSee('نرتّب ثقل البنود على أربع درجات');
        $response->assertSee('حكم منهجي منّا نراجعه، لا رقم مشتقّ من بيانات حملات');
        $response->assertSee('بندًا انطبقت عليك');
    }

    #[Test]
    public function every_weight_declares_its_tier_and_rank(): void
    {
        [, $report] = $this->reportForCompletedRun();

        $content = $report->sections()->where('key', 'score')->firstOrFail()->content_json;
        $rows = $content['breakdown'];
        $tiers = ['مصيري', 'حاسم', 'مؤثر', 'مساند'];

        foreach ($rows as $row) {
            $this->assertContains($row['weight_tier'], $tiers);
            $this->assertSame(count($rows), $row['weight_rank_of']);
            $this->assertGreaterThanOrEqual(1, $row['weight_rank']);
            $this->assertLessThanOrEqual(count($rows), $row['weight_rank']);
        }

        // الرتبة حقيقة لا رأي: أثقل بند رتبته 1، والأخفّ آخرها.
        $heaviest = collect($rows)->sortByDesc('weight')->first();
        $this->assertSame(1, $heaviest['weight_rank']);

        // بندان بالوزن نفسه يتساويان في الرتبة، فلا تُختلق أفضلية بينهما.
        $byWeight = collect($rows)->groupBy('weight');

        foreach ($byWeight as $group) {
            $this->assertCount(1, $group->pluck('weight_rank')->unique());
        }
    }

    #[Test]
    public function the_stored_breakdown_carries_the_basis_of_each_number(): void
    {
        [, $report] = $this->reportForCompletedRun();

        $section = $report->sections()->where('key', 'score')->firstOrFail();
        $content = $section->content_json;
        $row = $content['breakdown'][0];

        $this->assertArrayHasKey('weights_basis', $content);
        $this->assertArrayHasKey('total_weight', $content);

        // الحصة والسؤال والإجابة تُحفظ مع التقرير، فلا تضيع إن تغيّرت الأداة لاحقًا.
        $this->assertArrayHasKey('share', $row);
        $this->assertArrayHasKey('question', $row);
        $this->assertArrayHasKey('answer_label', $row);
        $this->assertNotSame('', (string) $row['question']);

        // الحصص مجتمعة تساوي الدرجة الكاملة: لا وزن ضائع ولا مزدوج.
        $this->assertEqualsWithDelta(
            100.0,
            array_sum(array_column($content['breakdown'], 'share')),
            0.5,
        );
    }

    #[Test]
    public function a_graded_answer_publishes_the_ladder_it_was_graded_on(): void
    {
        [, $report] = $this->reportForCompletedRun();

        $content = $report->sections()->where('key', 'score')->firstOrFail()->content_json;

        $graded = collect($content['breakdown'])->firstWhere(fn (array $row) => ! empty($row['scale']));
        $this->assertNotNull($graded, 'يجب أن يحمل بند مُدرَّج واحد على الأقل سلّم تقديره.');

        // السلّم بنص الخيارات لا بمفاتيحها البرمجية: «أعرفه بدقة» لا «known».
        foreach ($graded['scale'] as $step) {
            $this->assertArrayHasKey('label', $step);
            $this->assertArrayHasKey('factor', $step);
            $this->assertNotSame($step['key'], $step['label'], 'السلّم يعرض المفتاح الخام بدل نص الخيار.');
        }
    }

    /**
     * @return array{0: User, 1: Report}
     */
    private function reportForCompletedRun(): array
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'متجر عسل']);
        $tool = Tool::where('key', 'marketing-score')->firstOrFail();
        $svc = app(ToolRunService::class);
        $run = $svc->start($project, $tool, $user);

        $svc->saveStep($run, 1, ['business_model' => 'services', 'description' => str_repeat('وصف واضح للخدمة ', 3), 'geography' => 'الرياض', 'monthly_budget' => 5000]);
        $svc->saveStep($run, 2, ['primary_goal' => 'leads', 'value_proposition' => 'نسلّم خلال 48 ساعة أو المبلغ يُعاد كاملًا', 'audience_clarity' => 'documented']);
        $svc->saveStep($run, 3, ['active_channels' => ['seo'], 'tracking_maturity' => 'basic', 'content_cadence' => 'weekly']);
        $svc->saveStep($run, 4, ['landing_experience' => 'basic', 'retention_motion' => 'manual', 'sales_cycle' => 'medium', 'known_cac' => 120]);

        $admin = User::factory()->create(['is_admin' => true]);
        $report = app(ManualReportService::class)->import($run->fresh(), $this->payload(), $admin);

        return [$user, $report];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $finding = fn (string $title) => [
            'title' => $title,
            'description' => 'شرح واضح لهذه النتيجة مبني على إجابات صاحب المشروع نفسه.',
            'severity' => 'high',
            'is_assumption' => false,
            'evidence' => 'من إجابته عن حالة القياس.',
            'recommendations' => [[
                'title' => 'خطوة تنفيذية واضحة',
                'description' => 'نفّذ هذه الخطوة خلال هذا الأسبوع بشكل محدد وقابل للقياس.',
                'impact' => 'high',
                'effort' => 'low',
            ]],
        ];

        return [
            'summary' => 'ملخص تنفيذي مكتوب بلغة صاحب المشروع يوضح أين هو الآن وما الذي يبدأ به فورًا.',
            'confidence' => 88,
            'assumptions' => [],
            'next_step' => [
                'title' => 'ابدأ بربط القياس',
                'description' => 'عرّف حدث الشراء واربطه بمصدر الزيارة قبل أي زيادة في الإنفاق.',
            ],
            'findings' => [
                $finding('لا تعرف من أين يأتي عملاؤك'),
                $finding('صفحة التحويل لا تقنع'),
                $finding('لا متابعة بعد أول شراء'),
            ],
        ];
    }
}
