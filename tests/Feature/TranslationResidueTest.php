<?php

namespace Tests\Feature;

use App\Modules\Shared\I18n\BladeTranslator;
use App\Modules\Shared\I18n\ResidueScanner;
use Tests\TestCase;

/**
 * الطبقة التي لم يكن أحد يقيسها.
 *
 * `i18n:audit` كان يقيس اكتمال الكتالوج ويُسمّيه «تغطية»، فأعلن ١٠٠٪
 * للغتين بينما ٣٦ شاشة تعرض عربيًّا داخل واجهة إنجليزية — لأن النصّ الذي
 * لا يدخل الكتالوج أصلًا لا يظهر في عدّاد النقص.
 *
 * كل اختبار هنا يحرس تسرّبًا وقع فعلًا وأُصلح، لا احتمالًا نظريًّا.
 */
class TranslationResidueTest extends TestCase
{
    public function test_no_arabic_survives_the_wrapper_in_displayed_positions(): void
    {
        $found = app(ResidueScanner::class)->scan();

        $summary = [];

        foreach ($found as $kind => $hits) {
            foreach (array_slice($hits, 0, 5) as $hit) {
                $summary[] = "[{$kind}] {$hit['file']}:{$hit['line']} — {$hit['text']}";
            }
        }

        $this->assertSame([], $summary, "نصوص عربية تصل الشاشة بلا ترجمة:\n".implode("\n", $summary));
    }

    public function test_framework_lines_resolve_in_every_enabled_locale(): void
    {
        $broken = app(ResidueScanner::class)->frameworkLines((array) config('locales.enabled'));

        $this->assertSame([], $broken, 'رسائل إطار تُعرض كمفاتيح خام: '.json_encode($broken, JSON_UNESCAPED_UNICODE));
    }

    /**
     * التغليف اليدوي يجب أن يدخل الكتالوج.
     *
     * المغلّف يتعرّف على `__('…')` ويتركها كما هي حتى لا يُنتج تغليفًا
     * مزدوجًا — وكان يتركها **دون تسجيل**. فكان `__('أُضيف :name')` —
     * وهو المخرج الوحيد لجملة تحمل متغيّرًا — نصًّا لا يدخل الكتالوج، فلا
     * يُترجَم أبدًا ولا يُعدّ ناقصًا. عطلٌ يبدو نجاحًا.
     */
    public function test_manually_wrapped_blade_strings_reach_the_catalogue(): void
    {
        $template = <<<'BLADE'
        @foreach (['a' => __('مقال')] as $key => $label)
            <option>{{ $label }}</option>
        @endforeach
        <p>{{ __('أُضيف :name', ['name' => $n]) }}</p>
        <script>var m = @js(__('نُسخت'));</script>
        BLADE;

        $keys = array_keys((new BladeTranslator)->extract($template, 'test.blade.php'));

        $this->assertContains('مقال', $keys);
        $this->assertContains('أُضيف :name', $keys);
        $this->assertContains('نُسخت', $keys);
    }

    /**
     * سمات `data-*` المعروضة داخل نطاق المسح.
     *
     * `data-label` ليست عقدًا برمجيًّا: `workspace.css` يعرضها بـ
     * `content: attr(data-label)`، فهي عناوين أعمدة الجداول على الجوال.
     */
    public function test_displayed_data_attributes_are_wrapped(): void
    {
        $attributes = (array) config('locales.scan.blade.attributes');

        foreach (['data-label', 'data-confirm', 'data-tour'] as $attribute) {
            $this->assertContains($attribute, $attributes, "{$attribute} خارج نطاق المسح — قيمته تُعرض للمستخدم.");
        }

        $rewritten = (new BladeTranslator)->rewrite('<td data-label="الحالة">x</td>', 'test.blade.php');

        $this->assertStringContainsString("__('الحالة')", $rewritten);
    }

    /**
     * البرومبتات والمعاجم محميّة من التغليف الشامل.
     *
     * الخطأ هنا عكسيّ: نصّ واجهة منسيّ يظهر عربيًّا — مزعج ومرئيّ. أما
     * برومبت مترجَم فيغيّر ما يُطلب من النموذج، ومعجم مترجَم يكسر المطابقة
     * نفسها — في اللغة الأخرى وحدها، بلا خطأ واحد في السجل.
     */
    public function test_prompts_and_lexicons_are_protected_from_wrapping(): void
    {
        $protected = (array) config('locales.scan.php.never_wrap');

        $this->assertNotEmpty($protected);

        foreach ($protected as $path) {
            $absolute = base_path($path);

            $this->assertTrue(
                is_file($absolute) || is_dir($absolute),
                "مسار محميّ لم يعد موجودًا: {$path}",
            );

            // القائمة تقبل مجلدًا كما تقبل ملفًا — `app/Modules/Reporting`
            // كلها مخرجات طباعة تُغلَّف بيدٍ لا بمسح.
            $before = $this->fingerprint($absolute);

            /*
             * الحماية تعني أن المسح الآلي لا يلمس الملف — لا أن التغليف
             * اليدوي ممنوع فيه. `TaskGuideDeveloper` أكثره برومبت وفيه
             * أرضية حتمية يقرأها المستخدم، فتُغلَّف بيدٍ ويبقى الملف محميًّا.
             */
            $this->artisan('i18n:wrap-php', ['path' => [$path]])->assertSuccessful();

            $this->assertSame(
                $before,
                $this->fingerprint($absolute),
                "{$path} محميّ في never_wrap ومع ذلك عدّله `i18n:wrap-php`.",
            );
        }
    }

    /**
     * بصمة محتوى مسار — ملفًّا كان أو شجرة.
     */
    private function fingerprint(string $absolute): string
    {
        if (is_file($absolute)) {
            return md5_file($absolute) ?: '';
        }

        $parts = [];

        foreach (\Symfony\Component\Finder\Finder::create()->files()->in($absolute)->name('*.php') as $file) {
            $parts[] = $file->getRelativePathname().':'.md5_file($file->getPathname());
        }

        sort($parts);

        return md5(implode('|', $parts));
    }
}
