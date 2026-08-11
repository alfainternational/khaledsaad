<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * إشارات اللغة لمحركات البحث.
 *
 * العطل الذي أوجد هذا الاختبار: كان `canonical` يُبنى بـ`url()->current()`
 * وهي تُسقط سلسلة الاستعلام. فصفحة `?lang=en` تُعلن أن نسختها القانونية
 * هي العربية، بينما `hreflang` يقول لجوجل إنها نسخة إنجليزية مستقلة.
 * إشارتان متناقضتان، وجوجل يحسمهما لصالح `canonical` — فتسقط الإنجليزية
 * والفرنسية من الفهرس كلتاهما.
 *
 * ولا يظهر ذلك في تصفّح ولا في اختبار عرض: الصفحات تعمل، والزائر يراها.
 * يظهر بعد أشهر في سؤال «لماذا لا تجلب اللغات حركة؟».
 */
class SeoLocaleSignalsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * الشرط الحاكم: رابط `hreflang` لكل لغة يجب أن يساوي `canonical`
     * تلك الصفحة حرفيًّا. اختلافهما هو العطل نفسه.
     */
    public function test_each_hreflang_target_declares_itself_canonical(): void
    {
        foreach (config('locales.enabled') as $locale) {
            $html = $this->get(route('pricing').'?lang='.$locale)->assertOk()->getContent();

            $canonical = $this->attribute($html, '<link rel="canonical" href="([^"]+)"');

            $this->assertNotNull($canonical, "[{$locale}] لا يوجد canonical.");

            preg_match('/<link rel="alternate" hreflang="'.preg_quote($locale, '/').'" href="([^"]+)"/', $html, $m);

            $this->assertSame(
                $canonical,
                $m[1] ?? null,
                "[{$locale}] رابط hreflang لا يطابق canonical — إشارتان متناقضتان لجوجل.",
            );
        }
    }

    public function test_the_source_locale_carries_no_language_parameter(): void
    {
        $html = $this->get(route('pricing'))->assertOk()->getContent();

        $this->assertStringNotContainsString(
            'lang=ar',
            (string) $this->attribute($html, '<link rel="canonical" href="([^"]+)"'),
            'العربية هي الافتراضي؛ `?lang=ar` يصنع عنوانًا ثانيًا لنفس المحتوى.',
        );
    }

    /**
     * معاملات التتبّع تُسقَط من القانوني.
     *
     * بلا ذلك يصير لكل حملة إعلانية عنوانٌ قانونيّ مستقل، فتتفتّت إشارات
     * الصفحة الواحدة على عشرات العناوين بدل أن تتجمّع على واحد.
     */
    public function test_tracking_parameters_never_reach_the_canonical(): void
    {
        $html = $this->get(route('pricing').'?lang=en&utm_source=x&fbclid=y')->assertOk()->getContent();

        $canonical = (string) $this->attribute($html, '<link rel="canonical" href="([^"]+)"');

        $this->assertStringContainsString('lang=en', $canonical);
        $this->assertStringNotContainsString('utm_source', $canonical);
        $this->assertStringNotContainsString('fbclid', $canonical);
    }

    public function test_x_default_points_at_the_source_locale(): void
    {
        $html = $this->get(route('pricing').'?lang=fr')->assertOk()->getContent();

        $default = $this->attribute($html, '<link rel="alternate" hreflang="x-default" href="([^"]+)"');

        $this->assertNotNull($default);
        $this->assertStringNotContainsString('lang=', (string) $default);
    }

    public function test_the_sitemap_declares_every_locale_for_every_url(): void
    {
        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        foreach (config('locales.enabled') as $locale) {
            $this->assertStringContainsString('hreflang="'.$locale.'"', $xml);
        }

        $this->assertStringContainsString('xmlns:xhtml="http://www.w3.org/1999/xhtml"', $xml);
        $this->assertStringContainsString('hreflang="x-default"', $xml);
    }

    /**
     * `llms.txt` ملفّ Markdown نصّي يقرأه نموذج لغوي.
     *
     * مغلّف Blade يضمّ الأسطر المتجاورة في جملة واحدة ويُهرّب المخرَج،
     * فكان الملفّ يصل بسطر واحد و`>` مكتوبة `&gt;` — أي أن الملفّ الذي
     * كُتب ليقرأه نموذج كان أقلّ ما فيه قابلية القراءة.
     */
    public function test_the_llm_guide_keeps_its_markdown_structure(): void
    {
        $body = $this->get('/llms.txt')->assertOk()->getContent();

        $this->assertStringNotContainsString('&gt;', $body, 'الرموز مهرَّبة — مرّ الملفّ بمغلّف القوالب.');
        $this->assertGreaterThan(10, substr_count($body, "\n"), 'الأسطر مضمومة — بنية Markdown ضاعت.');
        $this->assertStringContainsString('## ', $body);
    }

    public function test_the_site_graph_names_the_organization_and_its_search(): void
    {
        $html = $this->get(route('pricing').'?lang=en')->assertOk()->getContent();

        foreach (['"Organization"', '"WebSite"', '"SearchAction"'] as $needle) {
            $this->assertStringContainsString($needle, $html, "البيانات المنظّمة تنقص {$needle}.");
        }

        // المخرَج مُنسَّق (`JSON_PRETTY_PRINT`)، فالمسافة بعد النقطتين جزء منه.
        $this->assertMatchesRegularExpression(
            '/"inLanguage":\s*"en"/',
            $html,
            'الرسم البياني يُعلن لغة غير لغة الصفحة.',
        );
    }

    private function attribute(string $html, string $pattern): ?string
    {
        return preg_match('/'.$pattern.'/', $html, $m) === 1 ? $m[1] : null;
    }
}
