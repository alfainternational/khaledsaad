<?php

namespace App\Modules\Brain;

use App\Models\Project;
use App\Modules\Brain\Models\BrainEvent;
use App\Modules\Brain\Models\BrainFact;
use App\Modules\Shared\Evidence\EvidenceLevel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * الواجهة الوحيدة للكتابة في الدماغ.
 *
 * كل قدرة تكتب من هنا ولا تلمس جداول الدماغ مباشرة. السبب ليس أناقة معمارية:
 * قواعد التعاقب والتعارض تُطبَّق في مكان واحد، ولو كتبت كل قدرة بطريقتها
 * لظهرت حقائق بلا سلف، وتعارضات محسومة صامتًا، وحقائق تُحدَّث في مكانها
 * فيضيع التاريخ الذي يقوم عليه التنبيه كله.
 */
class BrainWriter
{
    /**
     * تسجيل حقيقة.
     *
     * السلوك على ثلاث حالات:
     *   - القيمة نفسها أيًّا كان مصدرها → لا شيء. إعادة تأكيد لا تغيير.
     *   - قيمة مختلفة من المصدر نفسه   → استبدال: يصير القديم مسبوقًا بالجديد.
     *   - قيمة مختلفة من مصدر آخر      → تعارض: تُحفظ الحقيقتان ويُسجَّل حدث.
     *
     * الحالة الثالثة هي جوهر §٩: لا نحسم أي المصدرين أصدق، لأن الحسم الصامت
     * يخفي معلومة حقيقية — أن النشاط يقول شيئًا وبياناته تقول غيره.
     *
     * أما الحالة الأولى فتشمل اختلاف المصدر عمدًا: **التعارض اختلافُ قول لا
     * اختلافُ قائل**. مصدران يقولان الشيء نفسه تأكيدٌ مستقل، ولو عُدّ تعارضًا
     * لغرِق سجل المراجعة في تنبيهات عن اتفاق — وأول ما يُفقد حينها هو
     * التعارض الحقيقي وسط الضجيج.
     */
    public function record(
        Project $project,
        string $key,
        mixed $value,
        EvidenceLevel $level,
        string $sourceModule,
        ?string $sourceReference = null,
        ?string $period = null,
        ?array $metadata = null,
        ?Carbon $observedAt = null,
    ): BrainFact {
        $hash = BrainFact::hash($value);
        $observedAt ??= now();

        return DB::transaction(function () use (
            $project, $key, $value, $level, $sourceModule,
            $sourceReference, $period, $metadata, $observedAt, $hash
        ): BrainFact {
            $current = BrainFact::query()
                ->where('project_id', $project->id)
                ->where('key', $key)
                ->active()
                ->lockForUpdate()
                ->orderByDesc('observed_at')
                ->first();

            if ($current !== null && $current->value_hash === $hash) {
                return $current;
            }

            $conflicting = $current !== null && $current->source_module !== $sourceModule;

            $fact = BrainFact::create([
                'project_id' => $project->id,
                'key' => $key,
                'value_json' => ['value' => $value],
                'value_hash' => $hash,
                'evidence_level' => $level,
                'source_module' => $sourceModule,
                'source_reference' => $sourceReference,
                'period' => $period,
                'metadata' => $metadata,
                'observed_at' => $observedAt,
            ]);

            if ($current === null) {
                return $fact;
            }

            if ($conflicting) {
                $this->event($project, BrainEvent::TYPE_FACT_CONFLICT, [
                    'key' => $key,
                    'existing_fact_id' => $current->id,
                    'existing_source' => $current->source_module,
                    'existing_level' => $current->evidence_level->value,
                    'incoming_fact_id' => $fact->id,
                    'incoming_source' => $sourceModule,
                    'incoming_level' => $level->value,
                ], $observedAt);

                return $fact;
            }

            $current->update(['superseded_by' => $fact->id]);

            $this->event($project, BrainEvent::TYPE_FACT_SUPERSEDED, [
                'key' => $key,
                'superseded_fact_id' => $current->id,
                'fact_id' => $fact->id,
                'source' => $sourceModule,
            ], $observedAt);

            return $fact;
        });
    }

    /**
     * سحب حقيقة: تراجَع المستخدم أو زال مصدرها.
     *
     * الصف يبقى بقيمته ويخرج من السريان وحده (§٩: لا تُحذف حقيقة). أن يجيب
     * أحدهم ثم يقول «لا أعرف» معلومة عن نضج نشاطه، لا فراغ يُمحى.
     *
     * `$onlyIfOwned` يقصر السحب على ما كتبه المصدر نفسه. الجامع الذي يمرّ على
     * كل مفاتيحه دوريًّا يسحب ما لم يعد يجده — ولو سحب حقيقة كتبها جامع آخر
     * لمحا قياسًا مستقلًّا لأن **مصدرًا ثالثًا** صمت. من لم يكتب لا يسحب.
     *
     * يعيد الحقيقة المسحوبة، أو null إن لم تكن هناك حقيقة سارية يملكها.
     */
    public function retract(
        Project $project,
        string $key,
        string $sourceModule,
        ?array $metadata = null,
        ?Carbon $retractedAt = null,
        bool $onlyIfOwned = false,
    ): ?BrainFact {
        $retractedAt ??= now();

        return DB::transaction(function () use ($project, $key, $sourceModule, $metadata, $retractedAt, $onlyIfOwned): ?BrainFact {
            $current = BrainFact::query()
                ->where('project_id', $project->id)
                ->where('key', $key)
                ->active()
                ->lockForUpdate()
                ->orderByDesc('observed_at')
                ->first();

            if ($current === null) {
                return null;
            }

            if ($onlyIfOwned && $current->source_module !== $sourceModule) {
                return null;
            }

            $current->update([
                'retracted_at' => $retractedAt,
                'retracted_by_module' => $sourceModule,
            ]);

            $this->event($project, BrainEvent::TYPE_FACT_RETRACTED, [
                'key' => $key,
                'fact_id' => $current->id,
                'source' => $sourceModule,
                'metadata' => $metadata,
            ], $retractedAt);

            return $current;
        });
    }

    /**
     * تسجيل حدث. النتيجة تُملأ لاحقًا عبر resolve().
     *
     * @param  array<string, mixed>|null  $body
     */
    public function event(
        Project $project,
        string $type,
        ?array $body = null,
        ?Carbon $occurredAt = null,
    ): BrainEvent {
        return BrainEvent::create([
            'project_id' => $project->id,
            'type' => $type,
            'body' => $body,
            'occurred_at' => $occurredAt ?? now(),
        ]);
    }

    /**
     * تسجيل نتيجة حدث سابق — هذا ما يجعل الدماغ يتعلّم لا يراكم فقط.
     */
    public function resolve(BrainEvent $event, string $outcome): BrainEvent
    {
        $event->update(['outcome' => $outcome]);

        return $event;
    }
}
