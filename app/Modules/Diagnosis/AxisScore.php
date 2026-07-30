<?php

namespace App\Modules\Diagnosis;

use App\Modules\Shared\Evidence\EvidenceLevel;
use App\Modules\Shared\Metrics\MetricKey;

/**
 * نتيجة محور واحد: الدرجة، التغطية، مستوى الدليل، والفجوات.
 *
 * قيمة غير قابلة للتغيير: الدرجة تُحسب مرة من لقطة، ومن يعدّلها بعد الحساب
 * يكسر إمكان إعادة إنتاجها.
 */
final class AxisScore
{
    /**
     * @param  array<int, array<string, mixed>>  $breakdown  تفصيل «لماذا هذه الدرجة»
     * @param  array<int, string>  $gaps  المدخلات الغائبة، بأسمائها كما يراها المستخدم
     */
    public function __construct(
        public readonly Axis $axis,
        public readonly int $score,
        public readonly float $coverage,
        public readonly EvidenceLevel $evidenceLevel,
        public readonly array $breakdown,
        public readonly array $gaps,
        /**
         * متوسط كفاية المدخلات المفتوحة في هذا المحور (0–100)، أو `null` حين لا
         * يحمل المحور مدخلًا مفتوحًا يُقاس.
         *
         * مقياس مستقل عن `axis_score` ولا يُخلط به: الأول يقول «ما مستوى نشاطك»
         * والثاني يقول «ما مستوى ما أخبرتنا به عن نشاطك». محورٌ درجته منخفضة
         * وكفاية مدخلاته عالية مشكلته حقيقية؛ والعكس مشكلته في البيانات.
         */
        public readonly ?int $inputFitness = null,
    ) {}

    /**
     * هل هذا المحور مؤهَّل لدخول `maturity_score`؟
     *
     * محور بلا مدخل واحد ليس محورًا بدرجة صفر — بل محور لم يُقَس. إدخاله
     * بصفر يخفض الدرجة الكلية بلا سبب حقيقي، ويجعل المستخدم يطارد رقمًا لا
     * يعكس نشاطه بل نقص بياناته. الفرق بين الاثنين هو §٤.٣ بعينه.
     */
    public function isActive(): bool
    {
        return $this->coverage > 0.0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'axis' => $this->axis->value,
            'label' => $this->axis->label(),
            'question' => $this->axis->question(),
            MetricKey::AXIS_SCORE => $this->score,
            MetricKey::AXIS_COVERAGE => $this->coverage,
            'evidence_level' => $this->evidenceLevel->value,
            'is_assumption' => $this->evidenceLevel->needsAssumptionBadge(),
            'active' => $this->isActive(),
            MetricKey::INPUT_FITNESS => $this->inputFitness,
            'breakdown' => $this->breakdown,
            'gaps' => $this->gaps,
        ] + $this->namedMetric();
    }

    /**
     * الاسم الرسمي للمقياس حين يكون للمحور اسم في §١٢ غير `axis_score`.
     *
     * المحور السابع يُنتج `readiness_score` — وهو مقياس مستقل في التعريفات
     * الرسمية ويُعرض في بطاقة الجاهزية وتقارير العميل بهذا الاسم. تركه
     * `axis_score` وحده يجعل اسمًا في `MetricKey` بلا منتج له، وهي المخالفة
     * التي يمنعها §١٢: اسم مقياس معرَّف ولا شيء يُصدره.
     *
     * @return array<string, int>
     */
    private function namedMetric(): array
    {
        return match ($this->axis) {
            Axis::AiReadiness => [MetricKey::READINESS_SCORE => $this->score],
            default => [],
        };
    }
}
