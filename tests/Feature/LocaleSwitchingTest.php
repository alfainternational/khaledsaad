<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * تبديل اللغة من طرف الزائر.
 *
 * ما يُحرَس هنا: أن العربية تبقى الافتراضي مهما قال متصفح الزائر، وأن
 * الاختيار الصريح يثبت فلا يعود الموقع عربيًّا عند أول رابط داخلي، وأن
 * المفتاح المفقود يُرجع العربية لا فراغًا.
 */
class LocaleSwitchingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * ترجمة معلومة نقيس عليها من مسار إضافي، بلا لمس `lang/en.json`
         * المبنيّ: اختبارٌ يكتب فوق مخرَج البناء يهدم عمل ساعة بصمت.
         */
        $path = storage_path('framework/testing/lang');
        File::ensureDirectoryExists($path);
        File::put($path.'/en.json', json_encode(
            ['نصّ اختباري' => 'Test string'],
            JSON_UNESCAPED_UNICODE,
        ));

        app('translator')->addJsonPath($path);
    }

    /**
     * الافتراضي عربية حتى مع متصفح إنجليزي: الزائر الخليجي كثيرًا ما
     * يحمل نظامًا إنجليزيًّا وهو يقرأ عربيًّا، فترقية الترويسة تعطي أغلب
     * السوق واجهةً لم يطلبها.
     */
    public function test_arabic_is_the_default_even_for_an_english_browser(): void
    {
        $response = $this->withHeader('Accept-Language', 'en-US,en;q=0.9')->get(route('pricing'));

        $response->assertOk();
        $this->assertSame('ar', app()->getLocale());
        $response->assertSee('dir="rtl"', false);
    }

    public function test_an_explicit_choice_switches_the_interface(): void
    {
        $response = $this->get(route('pricing').'?lang=en');

        $response->assertOk();
        $this->assertSame('en', app()->getLocale());
        $response->assertSee('dir="ltr"', false);
        $response->assertSee('lang="en"', false);
    }

    public function test_an_explicit_choice_is_remembered_in_a_cookie(): void
    {
        $this->get(route('pricing').'?lang=en')
            ->assertCookie(config('locales.detection.cookie'), 'en');
    }

    public function test_an_unsupported_locale_falls_back_to_the_source(): void
    {
        $this->get(route('pricing').'?lang=zz')->assertOk();

        $this->assertSame('ar', app()->getLocale());
    }

    public function test_a_signed_in_user_keeps_their_choice_across_devices(): void
    {
        $user = User::factory()->create(['locale' => null]);

        $this->actingAs($user)->get(route('pricing').'?lang=en')->assertOk();

        $this->assertSame('en', $user->fresh()->locale);

        // جهاز آخر: بلا كوكي، والتفضيل المحفوظ وحده هو ما يحسم.
        $this->flushSession();
        $this->actingAs($user->fresh())->get(route('pricing'))->assertOk();

        $this->assertSame('en', app()->getLocale());
    }

    /**
     * المفتاح هو النص العربي، فالمفتاح المفقود يُرجع العربية لا فراغًا.
     * هذه هي الضمانة التي تجعل ترجمةً ناقصة عيبًا مرئيًّا لا صفحةً فارغة.
     */
    public function test_a_missing_translation_falls_back_to_the_arabic_source(): void
    {
        app()->setLocale('en');

        $this->assertSame('Test string', __('نصّ اختباري'));
        $this->assertSame('نصّ لا ترجمة له', __('نصّ لا ترجمة له'));
    }

    public function test_every_enabled_locale_is_offered_in_the_switcher(): void
    {
        $response = $this->get(route('pricing'));

        foreach (config('locales.enabled') as $code) {
            $response->assertSee('hreflang="'.$code.'"', false);
        }
    }
}
