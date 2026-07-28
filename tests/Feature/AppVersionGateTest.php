<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * حارس عقد api/v1.
 *
 * لا إصدار ثانٍ للواجهة، فيتطوّر v1 في مكانه. هذه البوابة هي ما يجعل ذلك
 * آمنًا: النسخة القديمة تتلقى رسالة تحديث مفهومة بدل عقد مكسور.
 */
class AppVersionGateTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_zero_minimum_lets_every_client_through(): void
    {
        config()->set('mobile.min_supported_build', 0);

        // هذه هي الحالة المشحونة: البوابة موجودة وغير مفعّلة، فلا تمنع أحدًا
        // قبل أن تصل نسخة التطبيق التي ترسل الترويسة.
        $this->getJson(route('api.v1.public.bootstrap'))->assertOk();
        $this->withHeader('X-App-Build', '1')
            ->getJson(route('api.v1.public.bootstrap'))->assertOk();
    }

    #[Test]
    public function an_older_build_is_told_to_update_instead_of_getting_a_broken_contract(): void
    {
        config()->set('mobile.min_supported_build', 5);

        $response = $this->withHeader('X-App-Build', '4')
            ->getJson(route('api.v1.public.bootstrap'))
            ->assertStatus(426);

        $response->assertJsonPath('error', 'app_update_required');
        $response->assertJsonPath('meta.min_supported_build', 5);
        $response->assertJsonPath('meta.your_build', 4);
        $this->assertStringContainsString('حدّثه', $response->json('message'));
    }

    #[Test]
    public function a_client_that_predates_the_header_is_treated_as_outdated(): void
    {
        config()->set('mobile.min_supported_build', 5);

        // غياب الترويسة هو بالضبط حال النسخة المثبَّتة اليوم. تمريرها يجعل
        // الحدّ بلا أثر على من وُضع من أجله.
        $this->getJson(route('api.v1.public.bootstrap'))
            ->assertStatus(426)
            ->assertJsonPath('meta.your_build', 0);
    }

    #[Test]
    public function a_current_build_passes(): void
    {
        config()->set('mobile.min_supported_build', 5);

        $this->withHeader('X-App-Build', '5')
            ->getJson(route('api.v1.public.bootstrap'))->assertOk();
        $this->withHeader('X-App-Build', '9')
            ->getJson(route('api.v1.public.bootstrap'))->assertOk();
    }

    #[Test]
    public function the_shipped_app_build_is_not_below_the_enforced_minimum(): void
    {
        /*
         * عقد بين الملفين: رفع الحد فوق بناء النسخة المشحونة يمنع الوصول عن
         * كل مستخدم فورًا. هذا الاختبار يجعل ذلك الخطأ مستحيلًا بلا انتباه.
         */
        $this->assertLessThanOrEqual(
            (int) config('mobile.build'),
            (int) config('mobile.min_supported_build'),
            'الحد الأدنى أعلى من بناء التطبيق المشحون — سيمنع كل المستخدمين.',
        );
    }
}
