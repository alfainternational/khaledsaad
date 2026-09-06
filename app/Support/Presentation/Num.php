<?php

namespace App\Support\Presentation;

/**
 * موحّد عرض الأرقام في كل الواجهات (بند ٣١ من خطة الواجهات).
 *
 * قرار التوحيد: أرقام غربية (0-9) — هي السائدة فعليًا في الواجهة الحالية
 * وفي منتجات السوق السعودي الرقمية — بفواصل آلاف عربية، ونسبة مئوية
 * بعلامة ٪ العربية ملاصقة. أي شاشة تعرض رقمًا تمر من هنا، فلا يتفاوت
 * الشكل بين شاشتين.
 */
class Num
{
    /** عدد صحيح بفاصل آلاف: 12,900 */
    public static function int(int|float|null $value): string
    {
        if ($value === null) {
            return '—';
        }

        return number_format((float) $value, 0, '.', ',');
    }

    /**
     * نسبة مئوية: «41٪» بالعربية و«41%» بغيرها.
     *
     * علامة النسبة نفسها تختلف: العربية تكتب ٪ ملاصقةً، واللاتينية %.
     * ولذلك تمرّ عبر الترجمة كقالب لا تُلصق في الكود.
     */
    public static function pct(int|float|null $value, int $decimals = 0): string
    {
        if ($value === null) {
            return '—';
        }

        return __(':value٪', ['value' => number_format((float) $value, $decimals, '.', ',')]);
    }

    /**
     * نسبةٌ من صفر إلى واحد تُعرض مئويةً: `ratio(0.12)` ⇒ «12٪».
     *
     * الضرب في مئة يحدث هنا لا في القالب (INV-2). قد يبدو فرقًا شكليًّا،
     * لكنه ما يمنع أن يُكتب `round($x * 100)` في شاشة و`floor($x * 100)`
     * في أخرى — فيرى المستخدم ١٢٪ هنا و١١٪ هناك لرقم واحد.
     */
    public static function ratio(int|float|null $value, int $decimals = 0): string
    {
        if ($value === null) {
            return '—';
        }

        return self::pct((float) $value * 100, $decimals);
    }

    /** مبلغ بالريال: 3,000 ر.س */
    public static function money(int|float|null $value, string $currency = 'ر.س'): string
    {
        if ($value === null) {
            return '—';
        }

        return self::int($value).' '.$currency;
    }

    /**
     * رقم مع أساسه — قاعدة الدستور §13 «كل رقم يُعرض معه أساسه»:
     * Num::withBasis(12, 620, 'استعلام') ⇒ «12٪ من 620 استعلام»
     */
    public static function withBasis(int|float $percent, int $total, string $unit): string
    {
        /*
         * الوصل بـ`.` كان يثبّت ترتيب العربية: «١٢٪ من ٦٢٠ استعلام».
         * ترتيب اللغة الهدف ملكها لا ملكنا، فصار قالبًا بنوّاب تُعيد
         * اللغة ترتيبه كما تشاء دون أن يفقد الرقم أساسه (§١٣).
         */
        return __(':percent من :total :unit', [
            'percent' => self::pct($percent),
            'total' => self::int($total),
            'unit' => $unit,
        ]);
    }

    /**
     * عدد الأرصدة بتمييزٍ عربي صحيح.
     *
     * «تحتاج 5 رصيدًا» كانت تتكرر في الشاشة أحد عشر مرة لأن النص رُكِّب
     * بالسَّلسَلة: رقمٌ زائد اسمٌ مفرد. العربية تصرّف المعدود على ستّ
     * صيغ، والمدى الصريح هنا يختارها بلا اعتماد على قواعد اللغة الهدف —
     * فتترجم كل لغة أخرى الصيغ التي تملكها هي.
     */
    public static function credits(int $count): string
    {
        return trans_choice(
            '{0} لا رصيد|{1} رصيد واحد|{2} رصيدان|[3,10] :count أرصدة|[11,*] :count رصيدًا',
            $count,
            ['count' => self::int($count)],
        );
    }

    /** درجة من مئة: «41 من 100» */
    public static function score(int|float|null $value): string
    {
        if ($value === null) {
            return '—';
        }

        return __(':value من 100', ['value' => self::int($value)]);
    }
}
