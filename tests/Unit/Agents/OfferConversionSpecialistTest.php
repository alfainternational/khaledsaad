<?php

namespace Tests\Unit\Agents;

use App\Domain\AI\Kernel\Agents\Specialists\OfferConversionSpecialist;
use App\Domain\AI\Semantic\ArabicNormalizer;
use App\Domain\AI\Semantic\ConceptLexicon;
use App\Domain\AI\Semantic\LexicalSemanticMatcher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OfferConversionSpecialistTest extends TestCase
{
    private OfferConversionSpecialist $cro;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cro = new OfferConversionSpecialist(
            new LexicalSemanticMatcher(new ArabicNormalizer, new ConceptLexicon),
        );
    }

    #[Test]
    public function a_strong_offer_scores_high(): void
    {
        $result = $this->cro->analyze(
            'احجز استشارتك خلال 48 ساعة بـ 199 ريالاً، مع ضمان استرجاع كامل إن لم تصلك خطة واضحة.',
        );

        $this->assertGreaterThanOrEqual(80, $result['score']);
        $this->assertNotEmpty($result['strengths']);
    }

    #[Test]
    public function a_vague_filler_offer_scores_low(): void
    {
        $result = $this->cro->analyze('نقدّم حلول مبتكرة بجودة عالية لجميع العملاء.');

        $codes = array_column($result['findings'], 'code');
        $this->assertLessThan(50, $result['score']);
        $this->assertContains('filler', $codes);
        $this->assertContains('no_cta', $codes);
    }

    #[Test]
    public function it_flags_missing_risk_reversal_and_numbers(): void
    {
        $result = $this->cro->analyze('اطلب خدمتنا التسويقية المميزة للشركات الناشئة.');

        $codes = array_column($result['findings'], 'code');
        $this->assertContains('no_numbers', $codes);
        $this->assertContains('no_risk_reversal', $codes);
    }

    #[Test]
    public function empty_offer_is_flagged(): void
    {
        $result = $this->cro->analyze('  ');

        $this->assertSame(0, $result['score']);
        $this->assertSame('empty', $result['findings'][0]['code']);
    }
}
