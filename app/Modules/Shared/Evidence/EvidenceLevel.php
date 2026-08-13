<?php

namespace App\Modules\Shared\Evidence;

/**
 * تدرّج الدليل — المفردة الوحيدة المسموح بها في النظام كله.
 *
 * السبب: أغلب مخرجات التشخيص التسويقي فرضيات بطبيعتها، والخطر ليس أن تكون
 * فرضية بل أن تُعرض كحقيقة. قبل هذا النوع كانت القاعدة تحمل أربع مفردات
 * متنافسة (high/medium/low، وmeasured/estimated/unknown، ودرجة 0–100،
 * وراية is_assumption)، فاستحال عرض وسم «فرضية» موحّدًا على الشاشات.
 *
 * نوع محصور لا ثوابت: الثابت لا يمنع تمرير قيمة خامسة، والنوع يمنعها عند
 * حدّ الدالة.
 */
enum EvidenceLevel: string
{
    /** رُصد فعلًا من مصدر مستقل عن كلام المستخدم. */
    case Measured = 'measured';

    /** حساب من بيانات مرصودة. */
    case Derived = 'derived';

    /** فرضية أو ارتباط أو رأي منهجي. */
    case Inferred = 'inferred';

    /**
     * التسمية العربية كما تظهر للمستخدم.
     */
    public function label(): string
    {
        return match ($this) {
            self::Measured => __('مقيس'),
            self::Derived => __('محسوب'),
            self::Inferred => __('فرضية'),
        };
    }

    /**
     * هل يحتاج هذا المستوى وسمًا بصريًا وكلمة «فرضية»؟
     *
     * قاعدة الواجهة: كل مخرج inferred يحمل الوسم. المقيس والمحسوب لهما
     * أساس معروض بدل الوسم.
     */
    public function needsAssumptionBadge(): bool
    {
        return $this === self::Inferred;
    }

    /**
     * قوة المستوى. الأعلى أقوى. للمقارنة الداخلية فقط، ولا يُعرض للمستخدم
     * كي لا يصير درجة يقين ثانية بجانب المستوى.
     */
    public function strength(): int
    {
        return match ($this) {
            self::Measured => 3,
            self::Derived => 2,
            self::Inferred => 1,
        };
    }

    /**
     * مستوى ناتج عن دمج عدة مدخلات: يأخذ أضعفها دائمًا.
     *
     * هذه هي الآلية التي تمنع فعليًا ترقية inferred إلى measured. حساب
     * مبنيّ على فرضية يظل فرضية مهما كانت دقة معادلته: رقم مشتق من تقدير
     * المستخدم لجمهوره لا يصير قياسًا لأننا قسمناه على رقم آخر.
     *
     * قائمة فارغة تعني «لا مدخل» لا «دليل مؤكد»، فتعود Inferred.
     *
     * @param  array<int, self>  $levels
     */
    public static function weakest(array $levels): self
    {
        if ($levels === []) {
            return self::Inferred;
        }

        return array_reduce(
            $levels,
            fn (self $carry, self $level) => $level->strength() < $carry->strength() ? $level : $carry,
            self::Measured,
        );
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
