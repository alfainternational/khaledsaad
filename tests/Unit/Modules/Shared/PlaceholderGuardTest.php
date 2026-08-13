<?php

namespace Tests\Unit\Modules\Shared;

use App\Modules\Shared\I18n\PlaceholderGuard;
use PHPUnit\Framework\TestCase;

/**
 * حارس النواب.
 *
 * كل حالة هنا وقعت فعلًا مع نماذج لغوية: ترجمة النائب، وحذفه، وإضافة
 * نائب لم يطلبه أحد. ثلاثتها تمرّ بلا استثناء وتُعرض للمستخدم رمزًا خامًّا.
 */
class PlaceholderGuardTest extends TestCase
{
    private PlaceholderGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new PlaceholderGuard;
    }

    public function test_matching_placeholders_pass(): void
    {
        $this->assertTrue($this->guard->isClean(
            'الخطوة :v1 من :v2',
            'Step :v1 of :v2',
        ));
    }

    public function test_reordered_placeholders_still_pass(): void
    {
        // ترتيب الكلمات ملك اللغة الهدف؛ الشرط هو بقاء النائب لا موضعه.
        $this->assertTrue($this->guard->isClean(
            'أنجزت :v1 من :v2 مهمة',
            ':v2 tasks total, :v1 done',
        ));
    }

    public function test_a_lost_placeholder_is_caught(): void
    {
        $violations = $this->guard->violations('الخطوة :v1 من :v2', 'Step :v1');

        $this->assertNotEmpty($violations);
        $this->assertStringContainsString(':v2', implode(' ', $violations));
    }

    public function test_a_translated_placeholder_is_caught(): void
    {
        $violations = $this->guard->violations('رصيدك :count نقطة', 'Your balance is :solde points');

        $this->assertNotEmpty($violations);
    }

    public function test_an_invented_placeholder_is_caught(): void
    {
        $violations = $this->guard->violations('ابدأ الآن', 'Start :now');

        $this->assertNotEmpty($violations);
    }

    public function test_html_must_survive_translation(): void
    {
        $this->assertTrue($this->guard->isClean('ابدأ <b>الآن</b>', 'Start <b>now</b>'));
        $this->assertNotEmpty($this->guard->violations('ابدأ <b>الآن</b>', 'Start now'));
    }

    /**
     * فاصل بنيوي ساقط يلصق جزأين: «Methodologyخالد سعد» في تبويب المتصفح.
     */
    public function test_a_dropped_structural_separator_is_caught(): void
    {
        $this->assertNotEmpty($this->guard->violations('منهجية العمل | خالد سعد', 'Methodology خالد سعد'));
        $this->assertTrue($this->guard->isClean('منهجية العمل | خالد سعد', 'Methodology | خالد سعد'));
    }

    public function test_an_em_dash_may_change_because_punctuation_differs_between_languages(): void
    {
        $this->assertTrue($this->guard->isClean('نتيجة — بلا ادعاء', 'A result, with no claim'));
    }

    public function test_empty_translation_is_a_violation(): void
    {
        $this->assertNotEmpty($this->guard->violations('نص', '   '));
    }
}
