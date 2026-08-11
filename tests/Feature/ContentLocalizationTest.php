<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\ContentTranslation;
use App\Modules\Learning\LessonTranslator;
use App\Modules\Shared\I18n\AiTranslator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentLocalizationTest extends TestCase
{
    use RefreshDatabase;

    private function lesson(array $overrides = []): Content
    {
        return Content::query()->create(array_merge([
            'type' => Content::TYPE_ARTICLE,
            'title' => 'التسويق التأثيري',
            'slug' => 'التسويق-التأثيري',
            'source_key' => 'marketing-course-19',
            'excerpt' => 'مقتطف عربي',
            'body_html' => '<p>نصّ عربي</p>',
            'source_text_hash' => str_repeat('a', 64),
            'status' => Content::STATUS_PUBLISHED,
            'access_level' => Content::ACCESS_PUBLIC,
            'published_at' => now(),
        ], $overrides));
    }

    public function test_lesson_follows_the_interface_locale_when_a_translation_exists(): void
    {
        $lesson = $this->lesson();

        ContentTranslation::query()->create([
            'content_id' => $lesson->id,
            'locale' => 'en',
            'title' => 'Influencer Marketing',
            'excerpt' => 'An English excerpt',
            'body_html' => '<p>English body</p>',
            'source_text_hash' => str_repeat('a', 64),
        ]);

        $this->app->setLocale('en');
        $lesson->localize();

        $this->assertSame('Influencer Marketing', $lesson->title);
        $this->assertSame('<p>English body</p>', $lesson->body_html);
        $this->assertSame('en', $lesson->displayLocale());
        $this->assertTrue($lesson->isTranslated());
        $this->assertFalse($lesson->hasStaleTranslation());
    }

    /**
     * الفجوة تُعلن ولا تُملأ: غياب الترجمة يُبقي الأصل ويصرّح بلغته،
     * فيستطيع القالب أن يعرض الوسم بدل أن يبدو المبدّل معطّلًا (§٤.٣).
     */
    public function test_untranslated_lesson_keeps_its_source_language_and_declares_it(): void
    {
        $lesson = $this->lesson();

        $this->app->setLocale('en');
        $lesson->localize();

        $this->assertSame('التسويق التأثيري', $lesson->title);
        $this->assertSame('ar', $lesson->displayLocale());
        $this->assertFalse($lesson->isTranslated());
    }

    public function test_translation_is_flagged_stale_when_the_arabic_source_changed_after_it(): void
    {
        $lesson = $this->lesson();

        ContentTranslation::query()->create([
            'content_id' => $lesson->id,
            'locale' => 'en',
            'title' => 'Influencer Marketing',
            'body_html' => '<p>English body</p>',
            // تُرجم من نسخة أقدم من الأصل الحالي.
            'source_text_hash' => str_repeat('b', 64),
        ]);

        $this->app->setLocale('en');
        $lesson->localize();

        $this->assertTrue($lesson->isTranslated());
        $this->assertTrue($lesson->hasStaleTranslation());
    }

    /**
     * `localize()` تكتب فوق السمات في الذاكرة. لو عدّها Eloquent تعديلًا
     * لكتب حفظٌ عابر النصَّ الإنجليزي في صفّ الأصل، فيضيع العربي بلا رجعة.
     */
    public function test_localize_never_marks_the_model_dirty(): void
    {
        $lesson = $this->lesson();

        ContentTranslation::query()->create([
            'content_id' => $lesson->id,
            'locale' => 'en',
            'title' => 'Influencer Marketing',
            'body_html' => '<p>English body</p>',
            'source_text_hash' => str_repeat('a', 64),
        ]);

        $this->app->setLocale('en');
        $lesson->localize();

        $this->assertFalse($lesson->isDirty());

        $lesson->save();

        $this->assertSame('التسويق التأثيري', $lesson->fresh()->title);
    }

    /**
     * أخطر ما في ترجمة الدرس ليس النص بل البنية: `id` الأقسام تُستعمل في
     * التنقّل الداخلي، والأصناف يقرؤها الجافاسكربت. أيّها ضاع، انكسرت
     * الصفحة في اللغة الأخرى وحدها بلا خطأ في السجل.
     */
    public function test_translating_a_lesson_preserves_markup_ids_and_attributes(): void
    {
        $html = '<section class="learning-section" data-x="keep">'
            .'<h2 id="section-1">عنوان القسم</h2>'
            .'<p>فقرة فيها <strong>كلمة بارزة</strong> ثم بقية.</p>'
            .'<table><tr><td>خلية</td></tr></table>'
            .'</section>';

        $translated = $this->fakeTranslator()->translateHtml($html, 'en');

        $this->assertStringContainsString('id="section-1"', $translated['html']);
        $this->assertStringContainsString('class="learning-section"', $translated['html']);
        $this->assertStringContainsString('data-x="keep"', $translated['html']);
        $this->assertStringContainsString('<strong>', $translated['html']);
        $this->assertStringContainsString('<td>', $translated['html']);

        $this->assertStringContainsString('EN:عنوان القسم', $translated['html']);
        $this->assertStringContainsString('EN:كلمة بارزة', $translated['html']);
        $this->assertStringNotContainsString('>عنوان القسم<', $translated['html']);

        $this->assertSame(0, $translated['missing']);
    }

    /**
     * المسافة حول النصّ ليست زخرفًا: بلا إعادتها تلتصق الكلمة بجارتها عبر
     * وسم داخلي فتقرأ «wordbold» بدل «word bold».
     */
    public function test_translation_restores_whitespace_around_inline_tags(): void
    {
        $translated = $this->fakeTranslator()->translateHtml('<p>قبل <em>وسط</em> بعد</p>', 'en');

        $this->assertStringContainsString('EN:قبل <em>EN:وسط</em> EN:بعد', $translated['html']);
    }

    /**
     * النصّ الذي تعذّرت ترجمته يبقى بأصله ويُعَدّ، فيستطيع أمر البناء أن
     * يرفض الحفظ بدل أن يخبز درسًا بلغتين.
     */
    public function test_untranslatable_text_is_kept_and_counted(): void
    {
        $translator = new class(app(AiTranslator::class)) extends LessonTranslator
        {
            protected function translateBatch(array $texts, string $locale, string $context): array
            {
                return ['translations' => [], 'failures' => []];
            }
        };

        $result = $translator->translateHtml('<p>فقرة</p>', 'en');

        $this->assertSame(0, $result['translated']);
        $this->assertSame(1, $result['missing']);
        $this->assertStringContainsString('فقرة', $result['html']);
    }

    private function fakeTranslator(): LessonTranslator
    {
        return new class(app(AiTranslator::class)) extends LessonTranslator
        {
            protected function translateBatch(array $texts, string $locale, string $context): array
            {
                $translations = [];

                foreach ($texts as $text) {
                    $translations[$text] = 'EN:'.$text;
                }

                return ['translations' => $translations, 'failures' => []];
            }
        };
    }
}
