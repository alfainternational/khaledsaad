<?php

namespace App\Modules\Shared\Sectors;

/**
 * القطاعات القانونية للمنصة — المصدر الوحيد للمفاتيح والتسميات.
 *
 * المنصة متخصصة في ثلاثة قطاعات (مواصفة `SECTOR-SPECIALIZATION.md`)، وتخدم
 * غيرها بالمسار العام. القطاع المعلن هنا يقين يصلح لإظهار أسئلة إلزامية
 * وفحوصات قطاعية، بخلاف القطاع المستنتَج من نص الوصف الذي يظل ترجيحًا.
 *
 * ممنوع تكرار هذه القوائم أو تسمياتها العربية في أي ملف آخر: من يحتاجها
 * يقرأها من هنا، وإلا عاد تشتّت المفردات الذي جاءت المواصفة لإنهائه.
 */
final class Sector
{
    public const EDUCATION = 'education';

    public const ECOMMERCE = 'ecommerce';

    public const REAL_ESTATE = 'real_estate';

    /** اختيار صريح من المستخدم أن نشاطه خارج قطاعات التخصص. */
    public const OTHER = 'other';

    /**
     * قطاعات التخصص الثلاثة — ما تَعِد المنصة بعمق حقيقي فيه.
     *
     * @var array<int, string>
     */
    public const SPECIALIZED = [self::EDUCATION, self::ECOMMERCE, self::REAL_ESTATE];

    /**
     * ما يُقبل في عمود `projects.sector`. غيابه (NULL) مشروع سبق المواصفة.
     *
     * @var array<int, string>
     */
    public const DECLARABLE = [self::EDUCATION, self::ECOMMERCE, self::REAL_ESTATE, self::OTHER];

    /**
     * التسمية العربية كما تظهر في الواجهة والتقارير.
     */
    public static function label(?string $sector): string
    {
        return match ($sector) {
            self::EDUCATION => 'التعليم',
            self::ECOMMERCE => 'التجارة الإلكترونية',
            self::REAL_ESTATE => 'العقارات',
            self::OTHER => 'قطاع آخر',
            default => 'غير محدد',
        };
    }

    /**
     * خيارات منتقي القطاع في الاستقبال (ويب وتطبيقًا) — الترتيب مقصود:
     * قطاعات التخصص أولًا ثم الباب المفتوح لغيرها.
     *
     * الشرح جزء من الخيار لا زخرفة: «التعليم» وحدها لا تخبر مركز تدريب
     * أنه المقصود أيضًا، والاختيار الخاطئ يقين خاطئ يبنى عليه تشخيص كامل.
     *
     * @return array<int, array{value: string, label: string, hint: string}>
     */
    public static function options(): array
    {
        return [
            [
                'value' => self::EDUCATION,
                'label' => self::label(self::EDUCATION),
                'hint' => 'مدرسة، جامعة، معهد، مركز تدريب، أو منصة تعليمية',
            ],
            [
                'value' => self::ECOMMERCE,
                'label' => self::label(self::ECOMMERCE),
                'hint' => 'متجر إلكتروني أو بيع عبر المنصات الوسيطة',
            ],
            [
                'value' => self::REAL_ESTATE,
                'label' => self::label(self::REAL_ESTATE),
                'hint' => 'وساطة، تطوير، تسويق عقاري، أو إدارة أملاك',
            ],
            [
                'value' => self::OTHER,
                'label' => self::label(self::OTHER),
                'hint' => 'أي نشاط آخر — تصلك كل القدرات بالمسار العام',
            ],
        ];
    }

    public static function isSpecialized(?string $sector): bool
    {
        return in_array($sector, self::SPECIALIZED, true);
    }

    /**
     * وصف النشاط كما يُعرض: القطاع المعلن أولًا والمجال الحر تفصيلًا بعده.
     *
     * **سبب وجودها:** خمس شاشات كانت تعرض `industry` وحده وتسقط إلى نص
     * «قطاع غير محدد» — وهو حرفيًّا ما تعيده `label(null)`. فمن اختار
     * «التعليم» بيده ثم ترك خانة المجال فارغة كانت لوحته تقول له إن قطاعه
     * غير محدد. الإعلان لم يكن يُخفى فحسب، بل يُناقَض.
     *
     * الترتيب مقصود: «التعليم — مدارس أهلية» تقرأ القطاع أولًا لأنه اليقين
     * الذي بُني عليه التشخيص، والتفصيل بعده لأنه تضييق لا تصنيف.
     */
    public static function describe(?string $sector, ?string $industry): string
    {
        $industry = trim((string) $industry);

        if (self::isSpecialized($sector)) {
            return $industry === ''
                ? self::label($sector)
                : self::label($sector).' — '.$industry;
        }

        // بلا إعلان يبقى النص الحر هو كل ما نعرفه، وغيابه يُقال صراحةً.
        return $industry === '' ? self::label($sector) : $industry;
    }

    /**
     * للقدرات المقيسة (كالتدقيق التقني): المعلن وحده يغيّر الفحص، وما عداه
     * يعامل بالمسار العام — فحص «measured» لا يُبنى على ترجيح نصي.
     */
    public static function declaredOrGeneral(?string $sector): string
    {
        return self::isSpecialized($sector) ? $sector : 'general';
    }
}
