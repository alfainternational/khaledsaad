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
        $this->assertArrayHasKey('knowledge', $brand);
        $this->assertCount(7, $brand['experience']);
        $this->assertSame('مدير التسويق', $brand['experience'][0]['role']);
        $this->assertSame(
            'مدير تسويق ومتخصص في التسويق التعليمي والحملات الاستراتيجية والتسويق القائم على البيانات',
            $brand['professional_headline'],
        );
        $this->assertNotEmpty($brand['professional_services']);
        $this->assertNotEmpty($brand['knowledge']);
        $this->assertNotEmpty($brand['principles']);
        $this->assertArrayHasKey('responsibilities', $brand['experience'][2]);
        $this->assertCount(5, $brand['experience'][2]['responsibilities']);
        $this->assertSame('جامعة النيلين', $brand['education'][0]['institution']);
        $this->assertContains('إدارة المشاريع الاحترافية PMP', $brand['credentials']);
    }
}
