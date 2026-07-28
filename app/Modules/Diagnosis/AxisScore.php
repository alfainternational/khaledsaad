<?php

namespace App\Modules\Diagnosis;

use App\Modules\Shared\Evidence\EvidenceLevel;

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
            'axis_score' => $this->score,
            'axis_coverage' => $this->coverage,
            'evidence_level' => $this->evidenceLevel->value,
            'is_assumption' => $this->evidenceLevel->needsAssumptionBadge(),
            'active' => $this->isActive(),
            'breakdown' => $this->breakdown,
            'gaps' => $this->gaps,
        ];
    }
}
