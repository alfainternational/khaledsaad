<?php

namespace Tests\Feature;

use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * الخطة المولّدة نتيجة تابعة، لا مقترح قيمة (§٢ و§١٥).
 *
 * السبب تجاري لا تحريري: التوليد متاح مجانًا على المنصة القائمة وفي كل أداة
 * منافسة. بيعُه يعني بيع ما لا يُملَك. ما يُملَك هو القياس الذي يسبقه، ولذلك
 * تُبنى الرسالة على التشخيص والفجوة والإصلاح.
 *
 * الاختبار على الصفحات العامة المفهرسة وحدها: هي ما يقرأه من لا يعرفنا بعد،
 * وهي التي تُشكّل التوقّع قبل أول نقرة.
 */
class PlanIsNotTheValuePropositionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * الصيغ التي تجعل الخطة مخرجًا يُشترى.
     *
     * كلمة «خطة» وحدها ليست ممنوعة — «خطة الاشتراك» و«الخطة المجانية» فوترة
     * لا منتج. المحظور أن تُقدَّم الخطة بوصفها ما يخرج به العميل.
     *
     * @var array<int, string>
     */
    private const FORBIDDEN = [
        'خطة تسويقية',
        'خطتك التسويقية',
        'خطة تسويق',
        'خطة 30 يومًا',
        'خطة ٣٠ يومًا',
        'احصل على خطة',
        'خطة جاهزة',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ToolCatalogSeeder::class);
    }

    #[Test]
    public function the_public_pages_sell_diagnosis_not_generation(): void
    {
        foreach ([route('home'), route('tools.index')] as $url) {
            $response = $this->get($url)->assertOk();

            foreach (self::FORBIDDEN as $phrase) {
                $response->assertDontSee($phrase);
            }
        }
    }

    #[Test]
    public function the_home_page_leads_with_the_measured_promise(): void
    {
        $response = $this->get(route('home'))->assertOk();

        // ليس تأكيدًا على نصّ بعينه بل على أن كلمة التشخيص حاضرة في الواجهة
        // الأولى — فالمنتج هو دقة التشخيص وتراكم الدماغ، وما عداهما واجهة.
        $response->assertSee('تشخيص', false);
    }
}
