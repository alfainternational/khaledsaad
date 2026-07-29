<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Modules\Brain\BrainWriter;
use App\Modules\Brain\Models\BrainEvent;
use App\Modules\Diagnosis\IndustryBenchmark;
use App\Modules\Shared\Metrics\MetricKey;
use App\Services\Projects\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * متوسط القطاع: مرجع أو لا شيء.
 *
 * ما يحرسه هذا الملف هو الامتناع لا الحساب. الرقم المعروض بلا عيّنة كافية
 * يكتسب سلطة مرجع وهو صدفة، وصاحب النشاط يقارن نفسه به ويقرر على أساسه.
 */
class IndustryBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    private IndustryBenchmark $benchmark;

    protected function setUp(): void
    {
        parent::setUp();
        $this->benchmark = app(IndustryBenchmark::class);
    }

    #[Test]
    public function a_thin_sample_yields_no_average_and_says_why(): void
    {
        $mine = $this->projectIn('التجزئة', 60);

        foreach ([50, 55, 70] as $score) {
            $this->projectIn('التجزئة', $score);
        }

        $result = $this->benchmark->for($mine);

        $this->assertFalse($result['available']);
        $this->assertStringContainsString('3', $result['reason']);
        $this->assertArrayNotHasKey('industry_average', $result);
    }

    #[Test]
    public function a_sufficient_sample_places_the_business_against_its_peers(): void
    {
        $mine = $this->projectIn('التجزئة', 70);

        foreach ([40, 50, 60, 60, 90] as $score) {
            $this->projectIn('التجزئة', $score);
        }

        $result = $this->benchmark->for($mine);

        // المتوسط: (40+50+60+60+90) ÷ 5 = 60
        $this->assertTrue($result['available']);
        $this->assertSame(5, $result['sample_size']);
        $this->assertSame(60, $result['industry_average']);
        $this->assertSame(10, $result['delta']);

        // أربعة من خمسة تحته → المئين ٨٠.
        $this->assertSame(80, $result['percentile']);
    }

    #[Test]
    public function a_frequently_measured_business_does_not_outweigh_the_others(): void
    {
        $mine = $this->projectIn('التجزئة', 70);
        $noisy = $this->projectIn('التجزئة', 20);

        // النشاط نفسه يُقاس تسع مرات إضافية بدرجة منخفضة.
        foreach (range(1, 9) as $ignored) {
            $this->scoreEvent($noisy, 20);
        }

        foreach ([80, 80, 80, 80] as $score) {
            $this->projectIn('التجزئة', $score);
        }

        $result = $this->benchmark->for($mine);

        // خمسة أنشطة: 20 + أربعة بـ80 = 340 ÷ 5 = 68. لا 26 كما لو عُدّت كل نقطة.
        $this->assertSame(5, $result['sample_size']);
        $this->assertSame(68, $result['industry_average']);
    }

    #[Test]
    public function businesses_from_other_industries_are_not_mixed_in(): void
    {
        $mine = $this->projectIn('التجزئة', 70);

        foreach ([10, 10, 10, 10, 10] as $score) {
            $this->projectIn('المطاعم', $score);
        }

        $result = $this->benchmark->for($mine);

        $this->assertFalse($result['available'], 'قطاع آخر دخل المتوسط، فصار المرجع بلا معنى.');
    }

    private function projectIn(string $industry, int $score): Project
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, [
            'name' => 'نشاط '.$industry.' '.$score,
            'industry' => $industry,
        ]);

        $this->scoreEvent($project, $score);

        return $project->fresh();
    }

    private function scoreEvent(Project $project, int $score): void
    {
        app(BrainWriter::class)->event($project, BrainEvent::TYPE_DIAGNOSIS_SCORED, [
            MetricKey::MATURITY_SCORE => $score,
            'score_coverage' => 1.0,
            'axes_active' => 2,
        ]);
    }
}
