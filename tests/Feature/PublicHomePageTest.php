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

    public function test_home_page_uses_the_reference_only_rtl_visual_contract_with_latin_digits(): void
    {
        $response = $this->get(route('home'))->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('<html lang="ar" dir="rtl">', $html);
        $this->assertStringContainsString('data-reference-ui="olex"', $html);
        $this->assertStringContainsString('hero-reference', $html);
        $this->assertStringContainsString('assets/design/hero-device-angle.png', $html);
        $this->assertStringContainsString('assets/design/hero-report-float.png', $html);
        $this->assertDoesNotMatchRegularExpression('/[٠١٢٣٤٥٦٧٨٩]/u', strip_tags($html));
    }

    public function test_home_page_gives_every_visual_section_unique_artwork_and_semantic_icons(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        preg_match_all('/data-section-art="([^"]+)"/', $html, $sectionArtwork);
        $this->assertCount(11, $sectionArtwork[1], 'Every visual section needs one artwork marker.');
        $this->assertCount(11, array_unique($sectionArtwork[1]), 'Section artwork must never be reused.');

        preg_match_all('/assets\/design\/([^"?]+)(?:\?[^" ]*)?/', $html, $designAssets);
        $this->assertNotEmpty($designAssets[1]);
        $this->assertCount(
            count($designAssets[1]),
            array_unique($designAssets[1]),
            'No design image may appear in more than one position on the page.'
        );
        foreach (array_unique($designAssets[1]) as $designAsset) {
            $this->assertFileExists(public_path('assets/design/'.$designAsset));
        }

        $this->assertGreaterThanOrEqual(20, substr_count($html, 'data-row-icon'));

        $referenceStyles = file_get_contents(resource_path('css/reference-ui.css'));
        $this->assertStringNotContainsString("'Cairo Display'", $referenceStyles);
    }

    public function test_home_page_artwork_is_transparent_png_and_visually_centered(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        preg_match_all('/assets\/design\/([^"?]+)(?:\?[^" ]*)?/', $html, $matches);
        $assets = array_values(array_unique($matches[1]));

        $this->assertNotEmpty($assets);

        foreach ($assets as $asset) {
            $this->assertStringEndsWith('.png', $asset, "{$asset} must be served as PNG.");

            $path = public_path('assets/design/'.$asset);
            $this->assertFileExists($path);

            $signatureAndHeader = file_get_contents($path, false, null, 0, 26);
            $this->assertSame("\x89PNG\r\n\x1a\n", substr($signatureAndHeader, 0, 8));
            $this->assertSame(6, ord($signatureAndHeader[25]), "{$asset} must use RGBA PNG color type.");

            $image = imagecreatefrompng($path);
            $this->assertNotFalse($image);
            $corners = [
                imagecolorat($image, 0, 0),
                imagecolorat($image, imagesx($image) - 1, 0),
                imagecolorat($image, 0, imagesy($image) - 1),
                imagecolorat($image, imagesx($image) - 1, imagesy($image) - 1),
            ];
            imagedestroy($image);

            $transparentCorners = array_filter(
                $corners,
                static fn (int $color): bool => (($color >> 24) & 0x7F) >= 120
            );
            $this->assertNotEmpty($transparentCorners, "{$asset} must contain a transparent outer background.");
        }

        $referenceStyles = file_get_contents(resource_path('css/reference-ui.css'));
        $this->assertStringContainsString("top: 50%;", $referenceStyles);
        $this->assertStringContainsString("object-position: center center;", $referenceStyles);
        $this->assertStringContainsString("translate: none !important;", $referenceStyles);
    }

    public function test_home_page_uses_the_approved_brand_artwork_and_matching_font_assets(): void
    {
        $this->assertFileExists(public_path('assets/brand/khaled-saad-approved.png'));

        /*
         * أربعة أوزان حقيقية لا ملفًا واحدًا مُصرَّحًا بمدى ١٠٠–٩٥٠.
         * ذاك التصريح كان يجعل المتصفح يعتبر الملف مغطّيًا لكل الأوزان فلا
         * يصنع عريضًا، فتُرسم كل العناوين بسُمك النص نفسه. الاختبار يحرس
         * وجود الأوزان كملفات مستقلة حتى لا تعود الهرمية إلى الصفر.
         */
        foreach ([400, 500, 600, 700] as $weight) {
            $this->assertFileExists(public_path("assets/fonts/plex/plex-ar-{$weight}.woff2"));
            $this->assertFileExists(public_path("assets/fonts/plex/plex-latin-{$weight}.woff2"));
        }

        $stylesheet = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString("'IBM Plex Sans Arabic'", $stylesheet);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-brand-logo="approved"', false)
            ->assertSee('assets/brand/khaled-saad-approved.png', false)
            ->assertSee('assets/fonts/plex/plex-ar-400.woff2', false)
            ->assertSee('assets/fonts/plex/plex-ar-700.woff2', false)
            ->assertDontSee('brand-logo__mark', false);
    }
}
