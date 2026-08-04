<?php

namespace Tests\Feature;

use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PublicHomePageTest extends TestCase
{
    // الصفحة العامة صارت تعرض كتالوج الأدوات من قاعدة البيانات،
    // فلم يعد اختبارها ممكنًا بلا مخطط وبيانات أدوات.
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ToolCatalogSeeder::class);
    }

    public function test_home_page_presents_the_full_marketing_journey_and_verified_profile(): void
    {
        $this->assertTrue(Route::has('home'), 'The public home route must be named home.');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('data-layout="marketing"', false)
            ->assertSee('layout-hero', false)
            ->assertSee('تجاوز إلى المحتوى')
            ->assertSee('خالد سعد | شخّص تسويق مشروعك وحدد أولوياتك')
            ->assertSee('ابدأ تشخيص مشروعك')
            ->assertSee('هل تصف إحدى هذه الحالات مشروعك؟')
            ->assertSee('ما الذي سيساعدك على اتخاذ قرار أوضح؟')
            ->assertSee('ما الذي تريد فهمه أو تحسينه الآن؟')
            ->assertSee('هكذا تساعدك النتيجة على اتخاذ القرار')
            ->assertSee('عني')
            ->assertSee('شركة الشمال التعليمية')
            ->assertSee('جامعة النيلين')
            ->assertSee('إدارة المشاريع الاحترافية PMP')
            ->assertSee('يلا نفهم تسويق')
            ->assertSee('الأسئلة الشائعة')
            // لغة العميل: لا حديث عن عدد الأدوات الجاهزة أو غير الجاهزة أمام العميل.
            ->assertDontSee('أداة تعمل الآن')
            ->assertDontSee('قيد البناء')
            ->assertSee('+966 53 305 2074')
            ->assertSee('https://wa.me/966533052074', false)
            ->assertSee('https://x.com/KhaledAASaad', false)
            ->assertSee('https://www.linkedin.com/in/khaledaasaad/', false)
            ->assertSee('application/ld+json', false);
    }

    public function test_home_page_excludes_unverified_or_private_profile_fields(): void
    {
        $this->assertTrue(Route::has('home'), 'The public home route must be named home.');

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('تاريخ الميلاد')
            ->assertDontSee('11111')
            ->assertDontSee('عميل موثوق')
            ->assertDontSee('نسبة نجاح');
    }

    public function test_home_page_uses_the_approved_brand_artwork_and_matching_font_assets(): void
    {
        $this->assertFileExists(public_path('assets/brand/khaled-saad-approved.png'));
        $this->assertFileExists(public_path('assets/fonts/Hacen-Tunisia.ttf'));
        $stylesheet = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString("'Hacen Tunisia', Tahoma, Arial, sans-serif", $stylesheet);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-brand-logo="approved"', false)
            ->assertSee('assets/brand/khaled-saad-approved.png', false)
            ->assertSee('assets/fonts/Hacen-Tunisia.ttf', false)
            ->assertDontSee('brand-logo__mark', false);
    }
}
