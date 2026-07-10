<?php

namespace Tests\Unit\AI;

use App\Domain\AI\Kernel\Agents\Specialists\OfferConversionSpecialist;
use App\Domain\AI\Kernel\Knowledge\KnowledgeStore;
use App\Domain\AI\Knowledge\MarketingKnowledgeBase;
use App\Domain\AI\Semantic\ArabicNormalizer;
use App\Domain\AI\Semantic\ConceptLexicon;
use App\Domain\AI\Semantic\LexicalSemanticMatcher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SemanticUnderstandingTest extends TestCase
{
    private function matcher(): LexicalSemanticMatcher
    {
        return new LexicalSemanticMatcher(new ArabicNormalizer, new ConceptLexicon);
    }

    #[Test]
    public function normalizer_unifies_hamza_taa_diacritics_and_digits(): void
    {
        $n = new ArabicNormalizer;
        $this->assertSame('احمد', $n->normalize('أحمَد'));   // همزة + تشكيل
        $this->assertSame('خدمه', $n->normalize('خدمة'));    // تاء مربوطة
        $this->assertSame('علي', $n->normalize('على'));      // ألف مقصورة
        $this->assertSame('5 ايام', $n->normalize('٥ أيام')); // أرقام عربية
    }

    #[Test]
    public function matcher_understands_paraphrase_not_just_keywords(): void
    {
        $m = $this->matcher();

        // عكس المخاطرة بلا كلمة «ضمان» — كان مستحيلاً في المطابقة المعجمية.
        $this->assertTrue($m->expresses('تدفع فقط لو رضيت عن النتيجة', 'risk_reversal'));
        $this->assertTrue($m->expresses('نعيد لك أموالك إن لم تعجبك الخدمة', 'risk_reversal'));

        // دعوة فعل وحشو.
        $this->assertTrue($m->expresses('احجز مكانك اليوم', 'cta'));
        $this->assertTrue($m->expresses('نقدّم جودة عالية واحترافية', 'filler'));
    }

    #[Test]
    public function matcher_does_not_false_positive(): void
    {
        $m = $this->matcher();
        $this->assertFalse($m->expresses('منتج ممتاز للبيع في المتجر', 'risk_reversal'));
        $this->assertFalse($m->expresses('نصمّم لك هوية بصرية مميزة', 'cta'));
        $this->assertSame(0.0, $m->strength('', 'risk_reversal'));
    }

    #[Test]
    public function similarity_reflects_meaning_overlap(): void
    {
        $m = $this->matcher();
        $close = $m->similarity('العميل يتردّد عند الدفع بسبب الثقة', 'الزبون متردّد في الدفع لعدم الثقة');
        $far = $m->similarity('العميل يتردّد عند الدفع', 'تصميم شعار وهوية بصرية');

        $this->assertGreaterThan($far, $close);
        $this->assertGreaterThan(0.0, $close);
    }

    #[Test]
    public function offer_specialist_credits_semantic_risk_reversal(): void
    {
        $spec = new OfferConversionSpecialist($this->matcher());
        $result = $spec->analyze('استشارة تسويقية خلال 3 أيام، تدفع فقط لو رضيت، ابدأ الآن');

        // عرض قوي: أرقام + عكس مخاطرة (بالمعنى) + دعوة فعل → درجة عالية بلا نواقص جوهرية.
        $this->assertGreaterThanOrEqual(85, $result['score']);
        $codes = array_column($result['findings'], 'code');
        $this->assertNotContains('no_risk_reversal', $codes);
        $this->assertNotContains('no_cta', $codes);
    }

    #[Test]
    public function knowledge_base_retrieves_relevant_pattern_and_benchmarks(): void
    {
        // KnowledgeStore مبدَّل بجذع لا يلمس التخزين (وحدة نقية).
        $store = new class extends KnowledgeStore
        {
            public function all(): array
            {
                return [];
            }
        };
        $kb = new MarketingKnowledgeBase($this->matcher(), $store);

        $hits = $kb->retrieve('العميل يتردّد بسبب الثقة عند الدفع', 2);
        $this->assertNotEmpty($hits);
        $this->assertStringContainsString('عكس المخاطرة', $hits[0]['text']);

        $bench = $kb->benchmarksFor('b2b_services');
        $this->assertSame(3.5, $bench['conversion_rate']);
        $this->assertContains('لينكدإن', $bench['primary_channels']);
    }
}
