<?php

namespace Tests\Unit\Agents;

use App\Domain\AI\Kernel\Agents\Specialists\SearchVisibilitySpecialist;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SearchVisibilitySpecialistTest extends TestCase
{
    private SearchVisibilitySpecialist $seo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seo = new SearchVisibilitySpecialist;
    }

    #[Test]
    public function well_optimized_content_scores_high(): void
    {
        $result = $this->seo->analyze(
            'خطة التسويق للمطاعم الصغيرة خطوة بخطوة',
            'خطة التسويق للمطاعم الصغيرة تبدأ بتحديد جمهورك المحلي. ما أول خطوة عملية؟ حدّد طبقك الأكثر ربحاً وابنِ حوله عرضك.',
            'خطة التسويق للمطاعم',
        );

        $this->assertGreaterThanOrEqual(80, $result['score']);
    }

    #[Test]
    public function it_flags_keyword_missing_from_title(): void
    {
        $result = $this->seo->analyze(
            'دليلك الشامل للنجاح',
            'نتحدث هنا عن التسويق الرقمي وأهميته للمشاريع.',
            'التسويق الرقمي',
        );

        $codes = array_column($result['findings'], 'code');
        $this->assertContains('keyword_title', $codes);
    }

    #[Test]
    public function it_flags_missing_answer_first_and_questions(): void
    {
        $result = $this->seo->analyze(
            'التسويق عبر البريد الإلكتروني',
            'في هذا المقال سنتحدث عن التسويق عبر البريد الإلكتروني بالتفصيل الممل دون أي جواب مباشر واضح.',
            'التسويق عبر البريد',
        );

        $codes = array_column($result['findings'], 'code');
        $this->assertContains('answer_first', $codes);
        $this->assertContains('no_question', $codes);
    }

    #[Test]
    public function it_labels_findings_by_engine_type(): void
    {
        $result = $this->seo->analyze('عنوان', 'في هذا المقال سنتحدث عن الموضوع.', 'كلمة');
        $types = array_unique(array_column($result['findings'], 'type'));

        foreach ($types as $type) {
            $this->assertContains($type, ['SEO', 'AEO', 'GEO']);
        }
    }
}
