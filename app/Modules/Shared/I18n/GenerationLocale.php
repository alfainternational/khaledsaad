<?php

namespace App\Modules\Shared\I18n;

/**
 * لغة المخرَج المولَّد — لا لغة الواجهة.
 *
 * سبب وجودها: كانت الواجهة تُترجَم والمنتج لا. القاعدة رقم ٢ في
 * `PipelineSchemas::systemPreamble()` تقول نصًّا «اكتب بلهجة بيضاء عربية»،
 * فكان من يختار الفرنسية يرى عناوين الأقسام والأزرار بلغته ثم **كل ما
 * يهمّه** بالعربية: التقرير، ودليل المهمة، ومقترح الرسالة. الترجمة كانت
 * قشرةً حول محتوى لم يتغيّر.
 *
 * ─── لماذا هنا لا في ثلاثة عشر برومبتًا ───
 *
 * في المنصة ثلاثة عشر موضعًا يبني برومبتًا. إضافة سطر لغة في كل واحد تعني
 * أن الموضع الرابع عشر — الذي يُكتب بعد شهر — سيولد عربيًّا بلا أن ينتبه
 * أحد، ولن يظهر ذلك في أي اختبار. فالتوجيه يُحقن في `StructuredRunner`،
 * وهو الحلق الذي يمرّ منه كل استدعاء منظَّم.
 *
 * ─── ما لا يُترجَم مخرَجه ───
 *
 * استعلامات الاستطلاع (`GatewayAnswerEngine`) تتجاوز `StructuredRunner`
 * وتنادي المزوّد مباشرةً، فهي معفاة بالبناء لا بالاستثناء: سؤال المشتري
 * يُطرح بالعربية لأنه يقيس الظهور في **السوق العربي**؛ ترجمته تقيس سؤالًا
 * آخر وتخالف §٤.٢. والمترجم نفسه معفى صراحةً بـ`localeNeutral`.
 */
final class GenerationLocale
{
    public function __construct(private readonly LocaleRegistry $locales) {}

    /**
     * توجيه اللغة الذي يُضاف إلى رسالة النظام.
     *
     * لغة المصدر تُرجع نصًّا فارغًا: البرومبتات مكتوبة بالعربية أصلًا،
     * فإضافة «اكتب بالعربية» إليها حشوٌ يستهلك سياقًا — والأهم أنه يضمن
     * أن المنتج العربي لا يتغيّر سلوكه بحرف واحد بسبب هذه القدرة.
     */
    public function directive(?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();

        if (! $this->locales->isEnabled($locale) || $locale === $this->locales->source()) {
            return '';
        }

        $name = $this->locales->englishName($locale);
        $native = $this->locales->nativeName($locale);
        $tone = $this->locales->generationTone($locale);

        return implode("\n", array_filter([
            "OUTPUT LANGUAGE — this overrides any language instruction above.",
            "Write every human-readable string in your JSON output in {$name} ({$native}).",
            $tone === '' ? null : "Tone: {$tone}",
            /*
             * الأمثلة الذهبية والقواعد أعلاه عربية، وهي ما يثبّت البنية
             * والنبرة. بلا هذا السطر ينسخ النموذج لغتها مع بنيتها فيخرج
             * المخرَج عربيًّا رغم التوجيه — وهو ما رأيناه فعلًا في التجربة.
             */
            'The rules and worked examples above are written in Arabic. '
                .'Follow their structure, their level of detail, and their reasoning — never their language.',
            /*
             * أسماء المقاييس عقد لا نصّ (§١٢). لو ترجمها النموذج بحرّية
             * لصار للمقياس اسمان: واحد في الشاشة وواحد في التقرير.
             */
            'Keep brand names, platform names, and metric names exactly as they appear in the input data.',
            'JSON keys stay exactly as the schema defines them. Only the values change language.',
        ]));
    }
}
