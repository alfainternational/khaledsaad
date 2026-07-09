<?php

namespace Tests\Unit\Agents;

use App\Domain\AI\Kernel\Agents\Specialists\LocalizationSpecialist;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class LocalizationSpecialistTest extends TestCase
{
    private LocalizationSpecialist $specialist;

    protected function setUp(): void
    {
        parent::setUp();
        $this->specialist = new LocalizationSpecialist;
    }

    #[Test]
    public function clean_arabic_scores_perfectly(): void
    {
        $result = $this->specialist->analyze('نحلّل مشروعك ونعطيك خطوات واضحة.');

        $this->assertSame(100, $result['score']);
        $this->assertSame([], $result['issues']);
    }

    #[Test]
    public function it_detects_and_strips_emojis(): void
    {
        $result = $this->specialist->analyze('ابدأ الآن 🚀 مع خطتك 🔥');

        $codes = array_column($result['issues'], 'code');
        $this->assertContains('emoji', $codes);
        $this->assertLessThan(100, $result['score']);
        $this->assertDoesNotMatchRegularExpression(
            '/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}]/u',
            $result['clean'],
        );
    }

    #[Test]
    public function it_converts_straight_quotes_to_arabic_guillemets(): void
    {
        $clean = $this->specialist->clean('اسم العرض "الباقة الذهبية" جاهز.');

        $this->assertStringContainsString('«الباقة الذهبية»', $clean);
        $this->assertStringNotContainsString('"', $clean);
    }

    #[Test]
    public function it_converts_latin_punctuation_after_arabic(): void
    {
        $clean = $this->specialist->clean('أولاً, ثم ثانياً; هل توافق?');

        $this->assertStringContainsString('أولاً،', $clean);
        $this->assertStringContainsString('ثانياً؛', $clean);
        $this->assertStringContainsString('توافق؟', $clean);
    }

    #[Test]
    public function it_collapses_extra_spacing_and_space_before_punctuation(): void
    {
        $clean = $this->specialist->clean('النتيجة   جاهزة .');

        $this->assertStringContainsString('النتيجة جاهزة.', $clean);
        $this->assertStringNotContainsString('  ', $clean);
    }

    #[Test]
    public function it_flags_latin_words_inside_arabic_text(): void
    {
        $result = $this->specialist->analyze('استخدم أداة marketing لبناء حملتك التسويقية الكاملة الآن.');

        $this->assertContains('latin_mix', array_column($result['issues'], 'code'));
    }

    #[Test]
    public function empty_text_returns_zero_without_error(): void
    {
        $result = $this->specialist->analyze('   ');

        $this->assertSame(0, $result['score']);
        $this->assertSame('', $result['clean']);
    }
}
