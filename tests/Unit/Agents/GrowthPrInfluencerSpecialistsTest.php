<?php

namespace Tests\Unit\Agents;

use App\Domain\AI\Kernel\Agents\Specialists\GrowthLoopSpecialist;
use App\Domain\AI\Kernel\Agents\Specialists\InfluencerSpecialist;
use App\Domain\AI\Kernel\Agents\Specialists\PrOutreachSpecialist;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class GrowthPrInfluencerSpecialistsTest extends TestCase
{
    #[Test]
    public function strong_growth_idea_scores_high(): void
    {
        $r = (new GrowthLoopSpecialist)->analyze(
            'حلقة إحالة: كل عميل يدعو صديقاً فيحصل الاثنان على خصم. نقيس عبر فرضية أن تكلفة الاكتساب تنخفض 30%.',
        );

        $this->assertGreaterThanOrEqual(80, $r['score']);
    }

    #[Test]
    public function growth_flags_missing_loop_and_unit_economics(): void
    {
        $r = (new GrowthLoopSpecialist)->analyze('سنزيد الإعلانات المدفوعة لجلب عملاء.');
        $codes = array_column($r['findings'], 'code');

        $this->assertContains('no_loop', $codes);
        $this->assertContains('no_unit_econ', $codes);
    }

    #[Test]
    public function strong_pr_pitch_scores_high(): void
    {
        $r = (new PrOutreachSpecialist)->analyze(
            'دراسة جديدة تكشف نمو 40% في التجارة الإلكترونية. هل تهتم بتغطية النتائج؟',
        );

        $this->assertGreaterThanOrEqual(80, $r['score']);
    }

    #[Test]
    public function pr_flags_missing_angle_and_proof(): void
    {
        $r = (new PrOutreachSpecialist)->analyze('نحن شركة تسويق ونحب أن تكتبوا عنا.');
        $codes = array_column($r['findings'], 'code');

        $this->assertContains('no_angle', $codes);
        $this->assertContains('no_proof', $codes);
    }

    #[Test]
    public function influencer_flags_missing_disclosure_as_high_severity(): void
    {
        $r = (new InfluencerSpecialist)->analyze('نريد مؤثّراً ينشر منشوراً عن منتجنا لمتابعيه.');
        $disclosure = collect($r['findings'])->firstWhere('code', 'no_disclosure');

        $this->assertNotNull($disclosure);
        $this->assertSame('high', $disclosure['severity']);
    }

    #[Test]
    public function strong_influencer_brief_scores_high(): void
    {
        $r = (new InfluencerSpecialist)->analyze(
            'بالتعاون مع مؤثّر يتطابق جمهوره مع فئتنا: منشور وريلز، مع كود خصم لقياس المبيعات.',
        );

        $this->assertGreaterThanOrEqual(85, $r['score']);
    }
}
