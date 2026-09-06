<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\Plan;
use App\Models\User;
use App\Services\Billing\Entitlements;
use App\Support\Billing\FeatureKey;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * تسميات الكتالوج تُخزَّن عربيةً وتُعرض بلغة القارئ.
 *
 * أسماء الباقات وعناصر الميزات نصوص واجهة تسكن قاعدة البيانات، فلا يراها
 * ماسح القوالب. كانت تصل الشاشة الإنجليزية عربيةً وحدها بين سطور مترجَمة،
 * بلا خطأ واحد يُنبِّه — ولا يكتشفها إلا من يفتح شاشة لا نفتحها نحن.
 */
class CatalogueTranslationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    #[Test]
    public function the_database_keeps_the_arabic_original_whatever_the_locale(): void
    {
        app()->setLocale('en');
        $this->seed(\Database\Seeders\FeatureSeeder::class);

        /*
         * أخطر عطل ممكن هنا: بذرٌ يجري ولغة الطلب إنجليزية فيخزّن الترجمة
         * مكان الأصل. عندها يصير المفتاح إنجليزيًّا، ولا تجد العربيةُ ترجمةً
         * لنفسها — فتنقلب الشاشة العربية إنجليزية بلا رجعة.
         */
        $this->assertSame(
            'ميزانية استعلامات الذكاء',
            Feature::where('key', FeatureKey::QUERY_BUDGET_MONTHLY)->value('name'),
        );
        $this->assertSame('مجانية', Plan::where('key', 'free')->value('name'));
    }

    #[Test]
    public function a_feature_label_follows_the_reader(): void
    {
        $feature = Feature::where('key', FeatureKey::QUERY_BUDGET_MONTHLY)->firstOrFail();

        app()->setLocale('ar');
        $this->assertSame('ميزانية استعلامات الذكاء', $feature->displayName());

        app()->setLocale('en');
        $this->assertSame('AI query budget', $feature->displayName());
    }

    #[Test]
    public function the_unit_is_translated_with_the_number_not_left_behind(): void
    {
        $feature = Feature::where('key', FeatureKey::PROJECTS_LIMIT)->firstOrFail();

        app()->setLocale('en');
        $described = $feature->describeValue(10);

        // «Projects — 10 مشروع» كان الناتج: رقمٌ إنجليزي بوحدة عربية.
        $this->assertStringContainsString('10', $described);
        $this->assertDoesNotMatchRegularExpression('/[\x{0600}-\x{06FF}]/u', $described);
    }

    #[Test]
    public function unlimited_is_a_word_not_a_leftover(): void
    {
        $feature = Feature::where('key', FeatureKey::TOOL_RUNS_MONTHLY)->firstOrFail();

        app()->setLocale('en');
        $this->assertDoesNotMatchRegularExpression(
            '/[\x{0600}-\x{06FF}]/u',
            $feature->describeValue(null),
        );
    }

    #[Test]
    public function the_billing_page_carries_no_arabic_for_an_english_reader(): void
    {
        $user = User::factory()->create();
        app()->setLocale('en');

        $labels = app(Entitlements::class)
            ->displayFeatures(Plan::where('key', 'professional')->firstOrFail());

        $this->assertNotEmpty($labels);

        foreach ($labels as $label) {
            $this->assertDoesNotMatchRegularExpression(
                '/[\x{0600}-\x{06FF}]/u',
                $label,
                "عنصر باقة يصل القارئ الإنجليزي عربيًّا: {$label}",
            );
        }
    }

    #[Test]
    public function the_public_pricing_page_carries_no_arabic_for_an_english_reader(): void
    {
        $html = $this->get(route('pricing', ['lang' => 'en']))->assertOk()->getContent();
        $items = $this->listItemsIn($html);

        $this->assertNotEmpty($items, 'الصفحة لم تعرض عناصر باقات أصلًا.');

        /*
         * فحص الصفحة لا الخدمة: هذه الصفحة تبني قائمة عناصرها بنفسها بدل
         * `Entitlements`، فترجمة الخدمة وحدها تركتها عربية — واختبارٌ يفحص
         * الخدمة كان سيمرّ أخضر بينما الشاشة معطوبة.
         */
        foreach ($items as $item) {
            $this->assertDoesNotMatchRegularExpression(
                '/[\x{0600}-\x{06FF}]/u',
                $item,
                "عنصر باقة يصل الزائر الإنجليزي عربيًّا: {$item}",
            );
        }
    }

    /** @return array<int, string> */
    private function listItemsIn(string $html): array
    {
        preg_match_all('/<li[^>]*>([^<]+)<\/li>/u', $html, $matches);

        return array_values(array_filter(array_map('trim', $matches[1] ?? [])));
    }

    #[Test]
    public function a_label_the_admin_invented_falls_back_to_his_own_words(): void
    {
        $feature = Feature::create([
            'key' => 'custom.thing',
            'name' => 'ميزة أنشأها الآدمن للتو',
            'group' => 'core',
            'type' => Feature::TYPE_BOOLEAN,
            'enforcement' => Feature::ENFORCEMENT_DISPLAY,
            'default_enabled' => true,
            'is_active' => true,
        ]);

        app()->setLocale('en');

        // بلا ترجمة مخبوزة يُعرض الأصل، ولا يُملأ الفراغ بتخمين (§٤.٣).
        $this->assertSame('ميزة أنشأها الآدمن للتو', $feature->displayName());
    }

    #[Test]
    public function platform_names_are_never_translated(): void
    {
        $feature = Feature::where('key', FeatureKey::REPORTS_PDF)->firstOrFail();

        app()->setLocale('en');
        $this->assertStringContainsString('PDF', $feature->displayName());

        app()->setLocale('fr');
        $this->assertStringContainsString('PDF', $feature->displayName());
    }
}
