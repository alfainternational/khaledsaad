<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Modules\Brain\BrainWriter;
use App\Modules\Diagnosis\ScoreHistory;
use App\Modules\Shared\Evidence\EvidenceLevel;
use App\Services\Projects\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * السلسلة الزمنية خاصيّة النشاط لا خاصيّة اشتراكه في المراقبة.
 *
 * العطل الذي يحرسه هذا الملف كان حقيقيًّا: تقييد النقطة كان معلّقًا داخل
 * `growth:watch`، وهو يمرّ على المراقبين النشطين وحدهم. نشاط مقيس لم يفعّل
 * تقريرًا حيًّا كانت سلسلته تبقى نقطةً واحدة إلى الأبد — فلا اتجاه، ولا
 * تنبيه، ولوحة الوكالة تقول «قياس واحد فقط» دائمًا.
 */
class DiagnosisPointScheduleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_measured_business_gets_its_point_without_any_report_watcher(): void
    {
        $project = $this->measuredProject();

        $this->artisan('diagnosis:record')->assertSuccessful();

        // لا مراقب ولا تقرير حي — ومع ذلك قُيِّدت النقطة.
        $this->assertSame(0, $project->reports()->count());
        $this->assertCount(1, app(ScoreHistory::class)->points($project));
    }

    #[Test]
    public function an_unmeasured_business_gets_no_point_at_all(): void
    {
        $project = $this->emptyProject();

        $this->artisan('diagnosis:record')->assertSuccessful();

        /*
         * سلسلة من أصفار تصنع «اتجاهًا ثابتًا» لنشاط لم يُقَس قط، ثم تقفز عند
         * أول قياس فتُقرأ تحسّنًا هائلًا لم يحدث.
         */
        $this->assertCount(0, app(ScoreHistory::class)->points($project));
    }

    #[Test]
    public function running_daily_does_not_produce_a_daily_series(): void
    {
        $project = $this->measuredProject();

        $this->artisan('diagnosis:record')->assertSuccessful();

        Carbon::setTestNow(now()->addDays(3));
        $this->artisan('diagnosis:record')->assertSuccessful();

        // الفاصل أسبوعي: أربع نقاط بهذا الفاصل = نافذة أربعة أسابيع (§٤.٢).
        $this->assertCount(1, app(ScoreHistory::class)->points($project));

        Carbon::setTestNow(now()->addDays(5));
        $this->artisan('diagnosis:record')->assertSuccessful();

        $this->assertCount(2, app(ScoreHistory::class)->points($project));

        Carbon::setTestNow();
    }

    #[Test]
    public function four_weekly_runs_make_the_history_plottable(): void
    {
        $project = $this->measuredProject();
        $history = app(ScoreHistory::class);

        foreach (range(0, 3) as $week) {
            Carbon::setTestNow(now()->addWeeks($week));
            $this->artisan('diagnosis:record')->assertSuccessful();
        }

        $this->assertTrue($history->isPlottable($project));

        Carbon::setTestNow();
    }

    private function measuredProject(): Project
    {
        $project = $this->emptyProject();

        app(BrainWriter::class)->record(
            $project, 'schema_organization', true, EvidenceLevel::Measured, 'AiReadiness',
        );

        return $project->fresh();
    }

    private function emptyProject(): Project
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'نشاط بلا مراقب']);
        $project->brainFacts()->delete();

        return $project->fresh();
    }
}
