<?php

namespace Tests\Feature;

use App\Models\Finding;
use App\Models\Plan;
use App\Models\Recommendation;
use App\Models\Report;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use App\Modules\Reporting\ReportPdfGenerator;
use App\Services\Billing\Entitlements;
use App\Services\Billing\SubscriptionManager;
use App\Services\Projects\ProjectService;
use App\Services\Tools\ToolRunService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    #[Test]
    public function it_generates_and_stores_a_pdf_once(): void
    {
        Storage::fake('local');

        $report = $this->report();
        $generator = app(ReportPdfGenerator::class);

        $path = $generator->ensure($report);

        Storage::disk('local')->assertExists($path);
        $this->assertNotNull($report->fresh()->pdf_generated_at);

        // النداء الثاني يعيد نفس المسار دون إعادة توليد.
        $this->assertSame($path, $generator->ensure($report->fresh()));
    }

    #[Test]
    public function the_owner_can_download_the_report_pdf(): void
    {
        $report = $this->report();
        $owner = $report->project->workspace->owner;

        // تصدير PDF عنصر ميزة: يبدأ من الخطة الفردية فصاعدًا.
        $this->onAPlanWithPdf($report);

        $this->actingAs($owner)
            ->get(route('app.reports.pdf', $report->id))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    #[Test]
    public function a_stranger_cannot_download_someone_elses_pdf(): void
    {
        $report = $this->report();
        $this->onAPlanWithPdf($report);

        $this->actingAs(User::factory()->create())
            ->get(route('app.reports.pdf', $report->id))
            ->assertNotFound();
    }

    /**
     * ترقية مساحة صاحب التقرير إلى خطة تشمل تصدير PDF.
     */
    private function onAPlanWithPdf(Report $report): void
    {
        app(SubscriptionManager::class)->subscribe(
            $report->project->workspace,
            Plan::where('key', 'individual')->firstOrFail(),
        );

        app(Entitlements::class)->flush();
    }

    private function report(): Report
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع PDF']);
        $tool = Tool::where('key', 'marketing-score')->firstOrFail();
        $run = app(ToolRunService::class)->start($project, $tool, $user);
        $run->forceFill(['status' => ToolRun::STATUS_COMPLETED, 'base_score' => 62])->save();

        $report = Report::create([
            'tool_run_id' => $run->id,
            'project_id' => $project->id,
            'title' => 'تقرير اختبار',
            'status' => 'published',
            'score' => 62,
            'score_band' => 'مستقر',
            'summary' => 'ملخص تجريبي للتقرير يكفي للعرض في الـPDF.',
            'next_step' => ['title' => 'ابدأ هنا', 'description' => 'خطوة أولى واضحة.'],
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

        Recommendation::create([
            'finding_id' => $finding->id,
            'report_id' => $report->id,
            'title' => 'ثبّت أداة تحليلات',
            'description' => 'أضف قياسًا خلال أسبوع.',
            'impact' => 'high',
            'effort' => 'low',
            'priority' => 80,
        ]);

        return $report->fresh();
    }
}
