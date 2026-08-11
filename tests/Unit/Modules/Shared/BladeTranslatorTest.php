<?php

namespace Tests\Unit\Modules\Shared;

use App\Modules\Shared\I18n\BladeTranslator;
use Tests\TestCase;

/**
 * مغلّف نصوص Blade.
 *
 * ما يُختبر هنا ليس «هل غلّف النص؟» بل الحالات التي يكون فيها التغليف
 * خطأً صامتًا: طرف مقارنة يُترجَم فينكسر الشرط، ووسم يتيم يُعرض خامًّا،
 * ونصّ داخل `@php` يُترجَم فيتحوّل مفتاح مصفوفة إلى نصّ إنجليزي.
 */
class BladeTranslatorTest extends TestCase
{
    private BladeTranslator $translator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->translator = new BladeTranslator;
    }

    public function test_it_wraps_plain_arabic_text_nodes(): void
    {
        $out = $this->translator->rewrite('<p>لوحة التحكم</p>');

        $this->assertSame("<p>{{ __('لوحة التحكم') }}</p>", $out);
    }

    public function test_it_leaves_text_without_arabic_untouched(): void
    {
        $out = $this->translator->rewrite('<p>Dashboard</p>');

        $this->assertSame('<p>Dashboard</p>', $out);
    }

    /**
     * الجملة الواحدة مفتاح واحد: تقطيعها عند كل متغيّر يعطي المترجم
     * شظايا لا جملة، فتخرج ترجمة ركيكة لا تُصلَح بمراجعة.
     */
    public function test_it_merges_variables_into_one_key_with_placeholders(): void
    {
        $out = $this->translator->rewrite('<p>الخطوة {{ $step }} من {{ $total }}</p>');

        $this->assertStringContainsString("__('الخطوة :v1 من :v2'", $out);
        $this->assertStringContainsString("'v1' => \$step", $out);
        $this->assertStringContainsString("'v2' => \$total", $out);
    }

    public function test_it_collapses_whitespace_so_indentation_does_not_create_a_second_key(): void
    {
        $inline = $this->translator->extract('<p>ابدأ الآن</p>');
        $wrapped = $this->translator->extract("<p>\n    ابدأ\n    الآن\n</p>");

        $this->assertSame(array_keys($inline), array_keys($wrapped));
        $this->assertSame(['ابدأ الآن'], array_keys($inline));
    }

    public function test_it_preserves_surrounding_whitespace(): void
    {
        $out = $this->translator->rewrite("<p>\n    ابدأ الآن\n</p>");

        $this->assertSame("<p>\n    {{ __('ابدأ الآن') }}\n</p>", $out);
    }

    public function test_it_wraps_user_facing_attributes_only(): void
    {
        $out = $this->translator->rewrite('<button aria-label="فتح القائمة" data-role="قائمة">x</button>');

        $this->assertStringContainsString('aria-label="{{ __(\'فتح القائمة\') }}"', $out);
        $this->assertStringContainsString('data-role="قائمة"', $out);
    }

    public function test_it_wraps_literals_inside_echo_expressions(): void
    {
        $out = $this->translator->rewrite('<span>{{ $isAdmin ? \'لوحة الإدارة\' : \'لوحة التحكم\' }}</span>');

        $this->assertStringContainsString("__('لوحة الإدارة')", $out);
        $this->assertStringContainsString("__('لوحة التحكم')", $out);
    }

    /**
     * أخطر حالة في الملف كله: ترجمة طرف المقارنة تكسر الشرط نفسه، ولا
     * تظهر إلا في اللغة الأخرى — أي في الشاشة التي لا يفتحها أحد منّا.
     */
    public function test_it_never_wraps_comparison_operands(): void
    {
        $out = $this->translator->rewrite('<span>{{ $sector === \'تعليم\' ? \'نعم\' : \'لا\' }}</span>');

        $this->assertStringContainsString("\$sector === 'تعليم'", $out);
        $this->assertStringNotContainsString("__('تعليم')", $out);
    }

    public function test_it_never_wraps_array_keys(): void
    {
        $out = $this->translator->rewrite("<span>{{ ['تعليم' => 1][\$key] }}</span>");

        $this->assertStringContainsString("['تعليم' => 1]", $out);
        $this->assertStringNotContainsString('__(', $out);
    }

    /**
     * السلسلة العربية داخل نداء دالة مفتاحُ بحثٍ في الغالب لا نصّ معروض.
     * ترجمتها تكسر البحث في اللغة الأخرى وحدها — عطل لا يظهر عربيًّا.
     */
    public function test_it_never_wraps_literals_inside_function_calls(): void
    {
        $out = $this->translator->rewrite("<span>{{ data_get(\$map, 'تعليم') }}</span>");

        $this->assertSame("<span>{{ data_get(\$map, 'تعليم') }}</span>", $out);
    }

    public function test_it_still_wraps_literals_inside_grouping_parentheses(): void
    {
        $out = $this->translator->rewrite("<span>{{ (\$flag) ? 'نعم' : 'لا' }}</span>");

        $this->assertStringContainsString("__('نعم')", $out);
        $this->assertStringContainsString("__('لا')", $out);
    }

    public function test_it_does_not_double_wrap_existing_translations(): void
    {
        $out = $this->translator->rewrite("<span>{{ __('مرحبًا') }}</span>");

        $this->assertSame("<span>{{ __('مرحبًا') }}</span>", $out);
    }

    public function test_it_skips_blade_comments(): void
    {
        $template = "{{-- تعليق عربي --}}\n<p>نص</p>";
        $out = $this->translator->rewrite($template);

        $this->assertStringContainsString('{{-- تعليق عربي --}}', $out);
        $this->assertStringNotContainsString("__('تعليق عربي')", $out);
        $this->assertStringContainsString("{{ __('نص') }}", $out);
    }

    /**
     * مصفوفات الأسئلة الشائعة وبطاقات الصفحات تُكتب في `@php` — وهي نصوص
     * يقرأها الزائر. تركها كلها كان يترك صفحة الأسعار عربيةً في واجهة
     * إنجليزية مكتملة، وهو أسوأ من صفحة عربية كاملة.
     */
    public function test_it_translates_display_strings_inside_php_blocks(): void
    {
        $template = "@php\n    \$faq = [['q' => 'كيف أبدأ؟', 'a' => 'من صفحة الأسعار.']];\n@endphp";
        $out = $this->translator->rewrite($template);

        $this->assertStringContainsString("'q' => __('كيف أبدأ؟')", $out);
        $this->assertStringContainsString("'a' => __('من صفحة الأسعار.')", $out);
    }

    public function test_the_short_php_directive_is_handled_too(): void
    {
        $out = $this->translator->rewrite("@php(\$label = 'ابدأ الآن')");

        $this->assertStringContainsString("@php(\$label = __('ابدأ الآن'))", $out);
    }

    /**
     * التغليف داخل `@php` لا يمسّ ما يكسر: التعليقات، ومفاتيح المصفوفات،
     * وأطراف المقارنة.
     */
    public function test_php_blocks_keep_comments_and_comparison_operands(): void
    {
        $template = "@php\n    // تعليق داخلي\n    \$x = \$sector === 'تعليم' ? 'نعم' : 'لا';\n@endphp";
        $out = $this->translator->rewrite($template);

        $this->assertStringContainsString('// تعليق داخلي', $out);
        $this->assertStringContainsString("\$sector === 'تعليم'", $out);
        $this->assertStringContainsString("__('نعم')", $out);
    }

    /**
     * تعليق Blade مكتوب بين سمات الوسم يبدأ بـ`{{` وينتهي بـ`}}`، فيبدو
     * نداءً. تمريره إلى مغلّف التعابير أنتج PHP معطوبًا انكسر عند العرض
     * لا عند التصريف — أي أن `php -l` لم يكشفه، وكشفه اختبار وظيفي.
     */
    public function test_it_leaves_blade_comments_written_inside_a_tag(): void
    {
        $template = "<main class=\"x\"\n    {{-- الجمهور يحدّد الكثافة --}}\n    data-a=\"1\">نص</main>";
        $out = $this->translator->rewrite($template);

        $this->assertStringContainsString('{{-- الجمهور يحدّد الكثافة --}}', $out);
        $this->assertStringContainsString("{{ __('نص') }}", $out);
    }

    /**
     * سمة قيمتها `{{ }}` تحتوي علامات اقتباس بداخلها.
     *
     * كانت تكسر تقسيم الوسم: القيمة المقتبسة تُغلَق عند أول `"` داخل
     * النداء، فيمتدّ الوسم إلى `>` الموجودة في `$feature->id` ويتناثر
     * السطر. النتيجة صفحة إدارة تعيد ٥٠٠ — وقد مرّت من `php -l` لأن
     * المقارنة كانت مع مصرّف يحمل المغلّف نفسه.
     */
    public function test_an_attribute_holding_an_echo_with_quotes_stays_intact(): void
    {
        $template = '<input value="{{ old("features.{$f->id}.value", $row?->value) }}" placeholder="بلا حد">';
        $out = $this->translator->rewrite($template);

        $this->assertStringContainsString('value="{{ old("features.{$f->id}.value", $row?->value) }}"', $out);
        $this->assertStringContainsString('placeholder="{{ __(\'بلا حد\') }}"', $out);
    }

    public function test_it_skips_script_and_style_content(): void
    {
        $out = $this->translator->rewrite('<script>const a = "نص";</script>');

        $this->assertSame('<script>const a = "نص";</script>', $out);
    }

    /**
     * الوسم داخل الجملة يبقى داخل المفتاح ليرى المترجم الجملة كاملة،
     * والإخراج غير مهرَّب وإلا عُرض الوسم حرفيًّا.
     */
    public function test_it_keeps_inline_markup_inside_the_key(): void
    {
        $out = $this->translator->rewrite('<p>ابدأ <b>الآن</b> معنا</p>');

        $this->assertStringContainsString("__('ابدأ <b>الآن</b> معنا')", $out);
        $this->assertStringContainsString('{!!', $out);
    }

    public function test_it_escapes_variables_when_output_is_unescaped(): void
    {
        $out = $this->translator->rewrite('<p>مرحبًا <b>{{ $name }}</b> بك</p>');

        $this->assertStringContainsString('e($name)', $out);
    }

    /**
     * وسمٌ فُتح خارج الجملة وأُغلق داخلها كان يُنتج `</b>` يتيمة داخل
     * المفتاح — ثم تُعرض للمستخدم نصًّا خامًّا لأن `{{ }}` تهرّبها.
     */
    public function test_it_does_not_leave_orphan_closing_tags_in_keys(): void
    {
        $keys = array_keys($this->translator->extract('<li><b class="n">1</b> إنشاء الحساب</li>'));

        foreach ($keys as $key) {
            $this->assertStringNotContainsString('</b>', $key);
        }

        $this->assertContains('إنشاء الحساب', $keys);
    }

    public function test_a_wrapping_tag_stays_outside_the_key(): void
    {
        $keys = array_keys($this->translator->extract('<p><em>نصّ مهم</em></p>'));

        $this->assertSame(['نصّ مهم'], $keys);
    }

    public function test_it_wraps_section_titles(): void
    {
        $out = $this->translator->rewrite("@section('title', 'الأسعار')");

        $this->assertStringContainsString("@section('title', __('الأسعار'))", $out);
    }

    /**
     * المفتاح المستخرَج يجب أن يطابق المفتاح المطلوب وقت العرض حرفًا
     * بحرف. لو انحرفا لظهرت ترجمة موجودة في الملف ولا تُعرض أبدًا.
     */
    public function test_extracted_keys_match_the_keys_the_rewrite_asks_for(): void
    {
        $template = <<<'BLADE'
        <div class="card" @class(['on' => $x])>
            <h2>عنوان القسم</h2>
            <p>أنجزت {{ $done }} من {{ $total }} مهمة.</p>
            <button aria-label="إغلاق">×</button>
            <span>{{ $flag ? 'نعم' : 'لا' }}</span>
        </div>
        BLADE;

        $out = $this->translator->rewrite($template);

        foreach (array_keys($this->translator->extract($template)) as $key) {
            $this->assertStringContainsString(
                "__('".str_replace(['\\', "'"], ['\\\\', "\\'"], $key)."'",
                $out,
                'المفتاح المستخرَج غير مطلوب في القالب المغلَّف: '.$key,
            );
        }
    }
}
