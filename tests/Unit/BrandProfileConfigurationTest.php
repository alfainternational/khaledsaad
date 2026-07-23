<?php

namespace Tests\Unit;

use Tests\TestCase;

class BrandProfileConfigurationTest extends TestCase
{
    public function test_brand_profile_exposes_verified_identity_and_contact_data(): void
    {
        $path = config_path('brand.php');

        $this->assertFileExists($path);

        $brand = require $path;

        $this->assertSame('خالد سعد', $brand['name']);
        $this->assertSame('+966533052074', $brand['contact']['phone']);
        $this->assertSame('https://www.linkedin.com/in/khaledaasaad/', $brand['contact']['linkedin']);
        $this->assertSame('https://x.com/KhaledAASaad', $brand['contact']['x']);
        $this->assertCount(7, $brand['experience']);
        $this->assertSame('جامعة النيلين', $brand['education'][0]['institution']);
        $this->assertContains('إدارة المشاريع الاحترافية PMP', $brand['credentials']);
    }
}
