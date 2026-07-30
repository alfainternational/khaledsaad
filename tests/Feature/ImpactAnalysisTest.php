<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Modules\Brain\Models\BrainEvent;
use App\Modules\Measurement\ImpactAnalyzer;
use App\Modules\Shared\Metrics\MetricKey;
use App\Services\Projects\ProjectService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * قياس الأثر المتقدم (المرحلة ٤، `SPEC-advanced-impact.md`).
 *
 * القاعدة التي يحرسها هذا الملف قبل الحساب: **الحركة مرصودة والسببية فرضية**.
 * بطاقة الأثر تقول «تحرّكت الإشارة بعد إصلاحك» لا «إصلاحك حرّكها»، وأي تسريب
 * لصيغة الجزم يخالف §٤.١. والبطاقة الناقصة النافذة تُطرح لا تُملأ بصفر (§٤.٣).
 */
class ImpactAnalysisTest extends TestCase
{
    use RefreshDatabase;

    /** لحظة قياس ثابتة: النوافذ حتمية وقابلة لإعادة الإنتاج. */
    private CarbonImmutable $asOf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asOf = CarbonImmutable::parse('2026-06-01T00:00:00Z');
    }

    #[Test]
    public function a_signal_that_moved_after_a_fix_produces_a_measured_delta(): void
    {
        $project = $this->project();
        $fixAt = $this->asOf->subDays(40);

        // درجتان قبل الإصلاح (متوسط ٥٠) واثنتان بعده (متوسط ٦٤).
        $this->score($project, 48, $fixAt->subDays(20));
        $this->score($project, 52, $fixAt->subDays(6));
        $this->intervention($project, 'geography', $fixAt);
        $this->score($project, 60, $fixAt->addDays(7));
        $this->score($project, 68, $fixAt->addDays(21));

        $cards = app(ImpactAnalyzer::class)->forProject($project, $this->asOf);

        $this->assertCount(1, $cards);
        $this->assertSame(MetricKey::MATURITY_SCORE, $cards[0]['signal']);
        $this->assertSame(50.0, $cards[0]['signal_before']);
        $this->assertSame(64.0, $cards[0]['signal_after']);
        $this->assertSame(14.0, $cards[0][MetricKey::SIGNAL_DELTA]);
    }

    #[Test]
    public function the_movement_is_derived_but_its_attribution_is_a_hypothesis(): void
    {
        $project = $this->project();
        $fixAt = $this->asOf->subDays(40);

        $this->score($project, 50, $fixAt->subDays(10));
        $this->intervention($project, 'positioning', $fixAt);
        $this->score($project, 60, $fixAt->addDays(10));

        $card = app(ImpactAnalyzer::class)->forProject($project, $this->asOf)[0];

        // الفصل بين طبقتي الدليل هو جوهر §٢ من المواصفة.
        $this->assertSame('derived', $card['delta_evidence']);
        $this->assertSame('inferred', $card['attribution_evidence']);
        $this->assertStringContainsString('لا سبب مثبت', $card['attribution_note']);
    }

    #[Test]
    public function an_open_after_window_is_not_measured_yet(): void
    {
        $project = $this->project();

        // الإصلاح قبل عشرة أيام فقط: نافذة «ما بعد» لم تُغلق (٢٨ يومًا).
        $recentFix = $this->asOf->subDays(10);
        $this->score($project, 50, $recentFix->subDays(10));
        $this->intervention($project, 'geography', $recentFix);
        $this->score($project, 70, $recentFix->addDays(3));

        $cards = app(ImpactAnalyzer::class)->forProject($project, $this->asOf);

        // لا بطاقة: الأثر لا يُعلَن قبل أن تنضج نافذته.
        $this->assertSame([], $cards);
    }

    #[Test]
    public function a_missing_window_is_declared_not_filled_with_zero(): void
    {
        $project = $this->project();
        $fixAt = $this->asOf->subDays(40);

        // درجات بعد الإصلاح فقط، ولا شيء قبله.
        $this->intervention($project, 'geography', $fixAt);
        $this->score($project, 60, $fixAt->addDays(7));
        $this->score($project, 66, $fixAt->addDays(21));

        $cards = app(ImpactAnalyzer::class)->forProject($project, $this->asOf);

        // نافذة «قبل» فارغة: البطاقة تُطرح، ولا تُحسب الحركة من صفر وهمي.
        $this->assertSame([], $cards);
    }

    #[Test]
    public function the_boundary_point_belongs_to_after_not_before(): void
    {
        $project = $this->project();
        $fixAt = $this->asOf->subDays(40);

        $this->score($project, 40, $fixAt->subDays(5));
        // نقطة على لحظة الإصلاح نفسها: تُنسب لما بعد الإصلاح.
        $this->score($project, 80, $fixAt);
        $this->intervention($project, 'geography', $fixAt);
        $this->score($project, 80, $fixAt->addDays(20));

        $card = app(ImpactAnalyzer::class)->forProject($project, $this->asOf)[0];

        $this->assertSame(40.0, $card['signal_before']);
        $this->assertSame(80.0, $card['signal_after']);
    }

    #[Test]
    public function no_signal_history_yields_no_cards(): void
    {
        $project = $this->project();
        $this->intervention($project, 'geography', $this->asOf->subDays(40));

        $this->assertSame([], app(ImpactAnalyzer::class)->forProject($project, $this->asOf));
    }

    private function project(): Project
    {
        $user = User::factory()->create();

        return app(ProjectService::class)->create($user, ['name' => 'نشاطي'])->fresh();
    }

    private function score(Project $project, int $value, CarbonImmutable $at): void
    {
        BrainEvent::create([
            'project_id' => $project->id,
            'type' => BrainEvent::TYPE_DIAGNOSIS_SCORED,
            'body' => [MetricKey::MATURITY_SCORE => $value],
            'occurred_at' => $at,
        ]);
    }

    private function intervention(Project $project, string $key, CarbonImmutable $at): void
    {
        BrainEvent::create([
            'project_id' => $project->id,
            'type' => BrainEvent::TYPE_FACT_SUPERSEDED,
            'body' => ['key' => $key],
            'occurred_at' => $at,
        ]);
    }
}
