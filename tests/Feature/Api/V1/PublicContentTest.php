<?php

namespace Tests\Feature\Api\V1;

use Database\Seeders\PlatformBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicContentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guest_overview_exposes_public_catalog_without_authentication(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);

        $response = $this->getJson('/api/v1/public/overview')->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data['hero']['title']);
        $this->assertCount(5, $data['paths']);
        $this->assertCount(5, $data['stages']);
        $this->assertSame(27, collect($data['stages'])->sum(fn (array $s): int => count($s['tools'])));
        $this->assertCount(10, $data['templates']);
        // الباقات النشطة فقط (إحداها غير نشطة في الكتالوج الافتراضي).
        $this->assertCount(5, $data['plans']);
        // لا شيء من بيانات المستخدمين أو المساحات في الاستجابة العامة.
        $this->assertArrayNotHasKey('workspaces', $data);
        $this->assertArrayNotHasKey('users', $data);
    }
}
