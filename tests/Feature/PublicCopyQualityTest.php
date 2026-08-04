<?php

namespace Tests\Feature;

use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCopyQualityTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_content_copy_explains_reader_benefit_not_publishing_internals(): void
    {
        $this->seed(ToolCatalogSeeder::class);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('محتوى يحول المعرفة إلى خطوات')
            ->assertSee('افهم المشكلة وطبّق خطوة واضحة')
            ->assertDontSee('من داخل المنصة')
            ->assertDontSee('أنشره هنا مباشرة')
            ->assertDontSee('فور نشره من لوحة الإدارة');
    }

    public function test_library_copy_explains_learning_and_action_not_platform_independence(): void
    {
        $this->get(route('content.index'))
            ->assertOk()
            ->assertSee('محتوى يساعدك على الفهم والتطبيق')
            ->assertSee('ابدأ التشخيص المجاني')
            ->assertDontSee('LinkedIn أو أي منصة خارجية')
            ->assertDontSee('فور نشره من لوحة الإدارة');
    }
}
