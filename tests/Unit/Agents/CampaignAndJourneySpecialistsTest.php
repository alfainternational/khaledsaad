<?php

namespace Tests\Unit\Agents;

use App\Domain\AI\Kernel\Agents\Specialists\CustomerJourneySpecialist;
use App\Domain\AI\Kernel\Agents\Specialists\PaidCampaignSpecialist;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CampaignAndJourneySpecialistsTest extends TestCase
{
    #[Test]
    public function strong_campaign_scores_high(): void
    {
        $r = (new PaidCampaignSpecialist)->analyze(
            'حملة تحويل تستهدف جمهور أصحاب المتاجر في الرياض بخصم 20%. اطلب الآن.',
        );

        $this->assertGreaterThanOrEqual(80, $r['score']);
    }

    #[Test]
    public function campaign_flags_missing_objective_audience_cta(): void
    {
        $r = (new PaidCampaignSpecialist)->analyze('نقدّم خدمة متميزة لعملائنا الكرام.');
        $codes = array_column($r['findings'], 'code');

        $this->assertContains('no_objective', $codes);
        $this->assertContains('no_cta', $codes);
        $this->assertContains('filler', $codes);
    }

    #[Test]
    public function empty_campaign_is_flagged(): void
    {
        $r = (new PaidCampaignSpecialist)->analyze('  ');

        $this->assertSame(0, $r['score']);
        $this->assertSame('empty', $r['findings'][0]['code']);
    }

    #[Test]
    public function journey_detects_covered_stages(): void
    {
        $r = (new CustomerJourneySpecialist)->analyze(
            'يبدأ العميل بالوعي عبر إعلان، ثم اهتمام ومقارنة، ثم قرار الشراء، ثم احتفاظ عبر متابعة ما بعد البيع. نقطة الاحتكاك عند الدفع. معدل التحويل 3%.',
        );

        $this->assertEqualsCanonicalizing(['الوعي', 'الاهتمام', 'القرار', 'الاحتفاظ'], $r['covered']);
        $this->assertGreaterThanOrEqual(85, $r['score']);
    }

    #[Test]
    public function journey_flags_missing_stages_and_friction(): void
    {
        $r = (new CustomerJourneySpecialist)->analyze('العميل يشتري المنتج مباشرة.');
        $codes = array_column($r['findings'], 'code');

        $this->assertContains('missing_stages', $codes);
        $this->assertContains('no_friction', $codes);
    }

    #[Test]
    public function empty_journey_is_flagged(): void
    {
        $r = (new CustomerJourneySpecialist)->analyze('');

        $this->assertSame(0, $r['score']);
        $this->assertSame([], $r['covered']);
    }
}
