<?php

namespace Tests\Unit;

use App\Support\Tooling\CanonicalOutputMapper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CanonicalOutputMapperTest extends TestCase
{
    private CanonicalOutputMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new CanonicalOutputMapper;
    }

    #[Test]
    public function it_maps_offer_builder_to_the_offer_key_with_the_real_value(): void
    {
        $result = $this->mapper->map('offer-builder', [
            'offer_name' => 'باقة الإطلاق السريع',
            'offer_result' => 'أول 10 عملاء خلال شهر',
        ]);

        $this->assertSame('offer', $result['key']);
        $this->assertSame('باقة الإطلاق السريع', $result['value']);
    }

    #[Test]
    public function it_falls_back_to_the_next_candidate_field_when_first_is_empty(): void
    {
        $result = $this->mapper->map('ideal-customer', [
            'customer_type' => '   ',
            'customer_problem' => 'تذبذب الطلبات',
        ]);

        $this->assertSame('ideal_customer', $result['key']);
        $this->assertSame('تذبذب الطلبات', $result['value']);
    }

    #[Test]
    public function it_returns_null_for_unmapped_tools(): void
    {
        $this->assertNull($this->mapper->map('diagnosis', ['main_goal' => 'x']));
    }

    #[Test]
    public function it_returns_null_when_all_candidate_fields_are_empty(): void
    {
        $this->assertNull($this->mapper->map('offer-builder', ['offer_name' => '', 'offer_result' => '  ']));
    }

    #[Test]
    public function key_for_exposes_the_canonical_key(): void
    {
        $this->assertSame('tagline', $this->mapper->keyFor('tagline-builder'));
        $this->assertSame('pricing', $this->mapper->keyFor('pricing-strategy'));
        $this->assertNull($this->mapper->keyFor('diagnosis'));
    }
}
