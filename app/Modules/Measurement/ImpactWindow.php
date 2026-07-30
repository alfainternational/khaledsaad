<?php

namespace App\Modules\Measurement;

use App\Modules\Shared\Evidence\EvidenceLevel;
use App\Modules\Shared\Metrics\MetricKey;
use Carbon\CarbonInterface;

/**
 * أثر إصلاح واحد على إشارة واحدة: نافذتان بمتوسطيهما والفرق بينهما.
 *
 * القيمة الجوهرية هنا **الفصل بين طبقتي الدليل** (المواصفة §٢):
 *   - `signal_delta` حركةٌ مرصودة على إشارة مملوكة → `derived`.
 *   - نسبتها إلى الإصلاح تزامنٌ زمنيّ لا سببية → `inferred` (فرضية).
 *
 * لذلك لا دالة `caused()` هنا ولا حقل «السبب»: البطاقة تعرض ما تحرّك ومتى،
 * وتترك الحكم للمستخدم. جملة سببية بصيغة الجزم تخالف §٤.١.
 */
final class ImpactWindow
{
    /**
     * @param  string  $signal  مفتاح الإشارة المقيسة (maturity_score مثلًا)
     * @param  string  $interventionLabel  ما فعله المستخدم
     * @param  int  $pointsBefore  عدد نقاط الإشارة في نافذة ما قبل
     * @param  int  $pointsAfter  عددها في نافذة ما بعد
     */
    public function __construct(
        public readonly string $signal,
        public readonly string $interventionLabel,
        public readonly CarbonInterface $interventionAt,
        public readonly ?float $signalBefore,
        public readonly ?float $signalAfter,
        public readonly int $pointsBefore,
        public readonly int $pointsAfter,
    ) {}

    /**
     * فرق المتوسطين، أو null إن لم تكتمل النافذتان.
     *
     * غياب طرف يُبقي الفرق غائبًا لا صفرًا: الصفر يُقرأ «لا أثر»، والغياب
     * يُقرأ «لا نعرف بعد» — وهما مختلفان (§٤.٣).
     */
    public function signalDelta(): ?float
    {
        if ($this->signalBefore === null || $this->signalAfter === null) {
            return null;
        }

        return round($this->signalAfter - $this->signalBefore, 2);
    }

    /**
     * هل اكتملت النافذتان بنقطة واحدة على الأقل في كلٍّ؟
     *
     * حدثٌ بلا قياس قبله أو بعده لا أثر له بعدُ. التغطية تُعلَن ناقصةً ولا
     * يُخترع رقم (§٤.٣).
     */
    public function isComplete(): bool
    {
        return $this->pointsBefore > 0 && $this->pointsAfter > 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'signal' => $this->signal,
            'intervention' => $this->interventionLabel,
            'intervention_at' => $this->interventionAt->toIso8601String(),
            'signal_before' => $this->signalBefore,
            'signal_after' => $this->signalAfter,
            MetricKey::SIGNAL_DELTA => $this->signalDelta(),
            'points_before' => $this->pointsBefore,
            'points_after' => $this->pointsAfter,
            'complete' => $this->isComplete(),

            /*
             * طبقتا الدليل صريحتان في المخرج لا في التعليق: الحركة derived،
             * ونسبتها للإصلاح inferred. الواجهة تسم الثانية «فرضية» (§١٣).
             */
            'delta_evidence' => EvidenceLevel::Derived->value,
            'attribution_evidence' => EvidenceLevel::Inferred->value,
            'attribution_note' => 'تزامنٌ زمنيّ لا سبب مثبت: تحرّكت الإشارة بعد إصلاحك، وقد يكون لسبب آخر.',
        ];
    }
}
