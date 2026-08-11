<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * رسائل الإطار — التحقق والترقيم والمصادقة واستعادة كلمة المرور.
 *
 * سبب وجود هذا الاختبار: هذه الرسائل كانت مكسورة في العربية نفسها. Laravel
 * يشحن الإنجليزية وحدها، فكان `validation.required` يُعرض للمستخدم حرفيًّا
 * تحت الحقل. لا استثناء يُرمى ولا سطر في السجل — العطل يظهر لمن يُخطئ في
 * ملء نموذج، لا لمن يقرأ الكود.
 *
 * والأهم: المطابقة النحوية. `:attribute` نائبٌ لا نعرف جنسه، و«كلمة المرور
 * مطلوب» خطأ لا يكشفه إلا قارئ عربي. تثبيت كلمة «حقل» قبله هو ما يجعل
 * المطابقة صحيحة دائمًا، وهذا الاختبار يحرسها.
 */
class FrameworkTranslationsTest extends TestCase
{
    /** @return array<int, array{0: string}> */
    public static function locales(): array
    {
        return [['ar'], ['en'], ['fr']];
    }

    #[DataProvider('locales')]
    public function test_validation_messages_are_never_raw_keys(string $locale): void
    {
        app()->setLocale($locale);

        $errors = Validator::make(
            ['email' => 'not-an-email'],
            ['email' => 'required|email', 'name' => 'required', 'credits' => 'required|integer'],
        )->errors()->all();

        $this->assertCount(3, $errors);

        foreach ($errors as $message) {
            $this->assertStringNotContainsString('validation.', $message,
                "[{$locale}] رسالة تحقق تُعرض كمفتاح خام: {$message}");
        }
    }

    #[DataProvider('locales')]
    public function test_framework_lines_outside_validation_resolve(string $locale): void
    {
        app()->setLocale($locale);

        foreach (['pagination.previous', 'pagination.next', 'passwords.sent', 'passwords.token', 'auth.failed'] as $key) {
            $this->assertNotSame($key, trans($key), "[{$locale}] {$key} يُعرض كمفتاح خام.");
        }
    }

    /**
     * أسماء الحقول تُعرض بلغة القارئ لا بأسماء الأعمدة.
     */
    #[DataProvider('locales')]
    public function test_field_names_are_readable_not_column_names(string $locale): void
    {
        app()->setLocale($locale);

        $message = Validator::make([], ['monthly_credits' => 'required'])->errors()->first();

        $this->assertStringNotContainsString('monthly_credits', $message,
            "[{$locale}] اسم العمود يُعرض خامًّا داخل رسالة التحقق.");

        // ما عدا الإنجليزية: «monthly credits» هي الصياغة الصحيحة فيها،
        // وظهورها في العربية أو الفرنسية يعني أن الخريطة لم تُقرأ.
        if ($locale !== 'en') {
            $this->assertStringNotContainsString('monthly credits', $message,
                "[{$locale}] لم تُقرأ خريطة `attributes` — الاسم بقي إنجليزيًّا.");
        }
    }

    /**
     * الجنس النحوي: اسم حقل مؤنّث يجب ألّا يُنتج «مطلوب» مذكّرًا.
     */
    public function test_arabic_agreement_holds_for_feminine_field_names(): void
    {
        app()->setLocale('ar');

        $message = Validator::make([], ['password' => 'required'])->errors()->first();

        $this->assertSame('حقل كلمة المرور مطلوب.', $message);
    }

    /**
     * تطابق المفاتيح بين اللغات الثلاث.
     *
     * ملف ناقص مفتاحًا واحدًا يعيد ذلك المفتاح خامًّا في لغة واحدة فقط —
     * أي في الشاشة التي لا يفتحها أحد منّا.
     */
    public function test_the_three_locales_carry_identical_keys(): void
    {
        $flatten = function (array $lines, string $prefix = '') use (&$flatten): array {
            $keys = [];

            foreach ($lines as $key => $value) {
                $keys = array_merge($keys, is_array($value)
                    ? $flatten($value, $prefix.$key.'.')
                    : [$prefix.$key]);
            }

            return $keys;
        };

        $ar = $flatten(require lang_path('ar/validation.php'));
        $en = $flatten(require lang_path('en/validation.php'));
        $fr = $flatten(require lang_path('fr/validation.php'));

        $this->assertSame([], array_values(array_diff($ar, $en)), 'مفاتيح في ar وليست في en');
        $this->assertSame([], array_values(array_diff($en, $ar)), 'مفاتيح في en وليست في ar');
        $this->assertSame([], array_values(array_diff($ar, $fr)), 'مفاتيح في ar وليست في fr');
        $this->assertSame([], array_values(array_diff($fr, $ar)), 'مفاتيح في fr وليست في ar');
    }
}
