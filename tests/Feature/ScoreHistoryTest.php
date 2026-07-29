<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Modules\Brain\BrainWriter;
use App\Modules\Diagnosis\MaturityAggregator;
use App\Modules\Diagnosis\ScoreHistory;
use App\Modules\Shared\Evidence\EvidenceLevel;
use App\Modules\Shared\Metrics\MetricKey;
use App\Services\Projects\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * تاريخ درجة النضج: ما يجعل التنبيه صادقًا.
 *
 * التاريخ المكذوب أسوأ من غيابه — على أساسه يقرّر صاحب النشاط أن يستمر أو
 * يتوقف. لذلك يحرس هذا الملف ثلاثة حدود: لا رسم قبل أربع نقاط، ولا نقطة قبل
 * أسبوع، ولا تنبيه على تغيّر سببه اتساع القياس لا تحسّن النشاط.
 */
class ScoreHistoryTest extends TestCase
{
    use RefreshDatabase;

    private ScoreHistory $history;

    private MaturityAggregator $maturity;

    private BrainWriter $brain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->history = app(ScoreHistory::class);
        $this->maturity = app(MaturityAggregator::class);
        $this->brain = app(BrainWriter::class);
    }

    #[Test]
    public function a_diagnosis_records_a_point_bound_to_its_snapshot(): void
    {
        $project = $this->measuredProject();

        $result = $this->maturity->computeAndSnapshot($project);
        $points = $this->history->points($project);

        $this->assertCount(1, $points);
        $this->assertSame($result[MetricKey::MATURITY_SCORE], $points->first()[MetricKey::MATURITY_SCORE]);

        // بلا الربط باللقطة يصير الرقم التاريخي ادّعاءً لا يمكن إثباته.
        $this->assertSame($result['brain_snapshot_id'], $points->first()['brain_snapshot_id']);
    }

    #[Test]
    public function three_points_are_not_a_trend(): void
    {
        $project = $this->measuredProject();

        foreach (range(1, 3) as $week) {
            Carbon::setTestNow(now()->addWeeks($week));
            $this->maturity->computeAndSnapshot($project);
        }

        $this->assertFalse($this->history->isPlottable($project));

        Carbon::setTestNow(now()->addWeek());
        $this->maturity->computeAndSnapshot($project);

        $this->assertTrue($this->history->isPlottable($project));

        Carbon::setTestNow();
    }

    #[Test]
    public function a_periodic_point_is_not_recorded_before_its_interval(): void
    {
        $project = $this->measuredProject();
        $this->maturity->computeAndSnapshot($project);

        Carbon::setTestNow(now()->addDays(6));
        $this->assertFalse($this->history->isDueForPoint($project));

        // سبعة أيام بالضبط: أربع نقاط بهذا الفاصل = نافذة أربعة أسابيع (§٤.٢).
        Carbon::setTestNow(now()->addDay());
        $this->assertTrue($this->history->isDueForPoint($project));

        Carbon::setTestNow();
    }

    #[Test]
    public function a_wider_measurement_is_not_reported_as_progress(): void
    {
        $project = $this->measuredProject();
        $this->maturity->computeAndSnapshot($project);

        // محور ثانٍ يدخل الحساب: الرقم يتحرّك لأن ما نقيسه اتّسع.
        $this->brain->record($project, 'owned_contacts', 1200, EvidenceLevel::Measured, 'OwnedAssets');
        $this->brain->record($project, 'total_reachable_audience', 5000, EvidenceLevel::Measured, 'OwnedAssets');

        Carbon::setTestNow(now()->addWeek());
        $this->maturity->computeAndSnapshot($project);

        $delta = $this->history->latestDelta($project);

        $this->assertNotNull($delta);
        $this->assertTrue($delta['coverage_changed'], 'اتساع التغطية لم يُعلَّم، فسيُقرأ كتقدّم.');

        Carbon::setTestNow();
    }

    /**
     * مشروع بمحور مقيس واحد، حتى تكون الدرجة محسوبة لا صفرًا.
     */
    private function measuredProject(): Project
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع التاريخ']);
        $project->brainFacts()->delete();
        $project = $project->fresh();

        $this->brain->record($project, 'schema_organization', true, EvidenceLevel::Measured, 'AiReadiness');
        $this->brain->record($project, 'ai_bots_allowed', true, EvidenceLevel::Measured, 'AiReadiness');

        return $project;
    }
}
