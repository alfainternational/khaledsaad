<?php

namespace Tests\Unit;

use App\Support\Tooling\MarketingConsistencyInspector;
use App\Support\Tooling\ProjectCanonicalFacts;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MarketingConsistencyInspectorTest extends TestCase
{
    private MarketingConsistencyInspector $inspector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inspector = new MarketingConsistencyInspector;
    }

    #[Test]
    public function it_flags_the_real_jeeblay_audience_drift(): void
    {
        // الحالة الحقيقية من الإنتاج: جمهور الملف (أصحاب متاجر) يناقض العميل المثالي (مغترب).
        $this->assertTrue($this->inspector->diverges(
            'سوداني مغترب في السعودية، 28–45 سنة، يعيل أسرة داخل السودان ويحوّل شهرياً',
            'أصحاب المشاريع التجارية متاجر الكترونية مطاعم وغيرهم',
        ));
    }

    #[Test]
    public function it_does_not_flag_aligned_audiences(): void
    {
        $this->assertFalse($this->inspector->diverges(
            'سوداني مغترب في السعودية يعيل أسرته داخل السودان',
            'المغترب السوداني الذي يعيل أسرته داخل السودان من السعودية',
        ));
    }

    #[Test]
    public function inspect_returns_a_structured_audience_finding(): void
    {
        $facts = $this->factsWithIdealCustomer('سوداني مغترب يعيل أسرته داخل السودان');

        $findings = $this->inspector->inspect($facts, [
            'audience' => 'أصحاب المشاريع التجارية متاجر الكترونية مطاعم',
        ]);

        $this->assertCount(1, $findings);
        $this->assertSame('audience_drift', $findings[0]['code']);
        $this->assertSame('audience', $findings[0]['field']);
        $this->assertSame('warning', $findings[0]['severity']);
        $this->assertStringContainsString('مغترب', $findings[0]['values']['canonical']);
        $this->assertStringContainsString('متاجر', $findings[0]['values']['profile']);
    }

    #[Test]
    public function inspect_is_silent_when_ideal_customer_is_missing(): void
    {
        $facts = $this->factsWithIdealCustomer(null);

        $this->assertSame([], $this->inspector->inspect($facts, [
            'audience' => 'أي جمهور',
        ]));
    }

    private function factsWithIdealCustomer(?string $idealCustomer): ProjectCanonicalFacts
    {
        return new class(0, null, $idealCustomer) extends ProjectCanonicalFacts
        {
            public function __construct(int $w, ?int $p, private readonly ?string $idealCustomer)
            {
                parent::__construct($w, $p);
            }

            public function value(string $key): ?string
            {
                return $key === 'ideal_customer' ? $this->idealCustomer : null;
            }
        };
    }
}
