<?php

namespace Tests\Unit\Agents;

use App\Domain\AI\Kernel\Agents\Specialists\LocalizationSpecialist;
use App\Domain\AI\Kernel\Agents\Specialists\OfferConversionSpecialist;
use App\Domain\AI\Kernel\Agents\Specialists\SearchVisibilitySpecialist;
use App\Domain\AI\Kernel\Agents\SpecialistReviewService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SpecialistReviewServiceTest extends TestCase
{
    private function service(): SpecialistReviewService
    {
        return new SpecialistReviewService(
            new LocalizationSpecialist,
            new OfferConversionSpecialist,
            new SearchVisibilitySpecialist,
        );
    }

    #[Test]
    public function it_runs_only_requested_aspects(): void
    {
        $result = $this->service()->review(
            'نص عربي واضح.',
            [SpecialistReviewService::ASPECT_LOCALIZATION],
        );

        $this->assertCount(1, $result['panels']);
        $this->assertSame('localization', $result['panels'][0]['key']);
        $this->assertIsInt($result['score']);
    }

    #[Test]
    public function it_aggregates_multiple_specialists_into_average_score(): void
    {
        $result = $this->service()->review(
            'احجز استشارتك خلال 48 ساعة بـ 199 ريالاً مع ضمان استرجاع كامل.',
            [SpecialistReviewService::ASPECT_LOCALIZATION, SpecialistReviewService::ASPECT_OFFER],
        );

        $this->assertCount(2, $result['panels']);
        $keys = array_column($result['panels'], 'key');
        $this->assertEqualsCanonicalizing(['localization', 'offer'], $keys);
        $this->assertGreaterThan(0, $result['score']);
    }

    #[Test]
    public function search_aspect_uses_title_and_keyword_meta(): void
    {
        $result = $this->service()->review(
            'خطة التسويق للمطاعم تبدأ بتحديد جمهورك. ما أول خطوة؟ حدّد طبقك الأكثر ربحاً.',
            [SpecialistReviewService::ASPECT_SEARCH],
            ['title' => 'خطة التسويق للمطاعم الصغيرة', 'keyword' => 'خطة التسويق للمطاعم'],
        );

        $this->assertSame('search', $result['panels'][0]['key']);
        $this->assertGreaterThanOrEqual(70, $result['panels'][0]['score']);
    }

    #[Test]
    public function empty_text_yields_no_panels(): void
    {
        $result = $this->service()->review('  ', [SpecialistReviewService::ASPECT_LOCALIZATION]);

        $this->assertNull($result['score']);
        $this->assertSame([], $result['panels']);
    }
}
