<?php

namespace Tests\Unit\Agents;

use App\Domain\AI\Kernel\Gate\OutputQualityGate;
use App\Domain\AI\Services\QualityJudge;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OutputQualityGateTest extends TestCase
{
    /**
     * قاضٍ مزيّف يتحكّم في المخرَج بلا أي مورد خارجي — لعزل منطق البوابة.
     *
     * @param  array{score: int, reason: string}|null  $verdict
     */
    private function gateWith(?array $verdict): OutputQualityGate
    {
        $judge = new class($verdict) extends QualityJudge
        {
            /** @param array{score: int, reason: string}|null $verdict */
            public function __construct(private readonly ?array $verdict)
            {
            }

            public function score(string $label, string $instructions, string $value): ?array
            {
                return $this->verdict;
            }
        };

        return new OutputQualityGate($judge);
    }

    #[Test]
    public function it_passes_high_quality_content(): void
    {
        $result = $this->gateWith(['score' => 85, 'reason' => 'محدّد وملموس'])
            ->assess('أداة العرض', 'أمهات 25-34 بالرياض يشترين عبر انستغرام');

        $this->assertSame('pass', $result['verdict']);
        $this->assertSame(85, $result['score']);
    }

    #[Test]
    public function it_warns_on_low_quality_content(): void
    {
        $result = $this->gateWith(['score' => 40, 'reason' => 'حشو عام'])
            ->assess('أداة العرض', 'نخدم جميع العملاء بأفضل جودة');

        $this->assertSame('warn', $result['verdict']);
        $this->assertSame(40, $result['score']);
    }

    #[Test]
    public function it_degrades_safely_without_blocking_when_judge_is_unavailable(): void
    {
        $result = $this->gateWith(null)->assess('أداة العرض', 'أي محتوى');

        // تعذّر التقييم (Kill Switch / لا LLM) ⇒ لا حجب صامت.
        $this->assertSame('pass', $result['verdict']);
        $this->assertNull($result['score']);
    }

    #[Test]
    public function it_flags_empty_output(): void
    {
        $result = $this->gateWith(['score' => 90, 'reason' => 'x'])->assess('أداة', '   ');

        $this->assertSame('empty', $result['verdict']);
        $this->assertFalse($this->gateWith(null)->passes('أداة', ''));
    }
}
