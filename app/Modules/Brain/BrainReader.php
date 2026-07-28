<?php

namespace App\Modules\Brain;

use App\Models\Project;
use App\Modules\Brain\Models\BrainEvent;
use App\Modules\Brain\Models\BrainFact;
use App\Modules\Brain\Models\BrainSnapshot;
use App\Modules\Shared\Evidence\EvidenceLevel;
use Illuminate\Support\Collection;

/**
 * قراءة السياق التجاري.
 *
 * أي قدرة تحتاج معلومة تسألها من هنا، ولا تعيد سؤال المستخدم عما يعرفه
 * النظام (§١٥). القارئ لا يتصل بالإنترنت ولا يستنتج — يعرض ما سُجّل ومستواه
 * وتاريخه فقط.
 */
class BrainReader
{
    /**
     * الحقائق السارية مفهرسة بالمفتاح.
     *
     * @return Collection<string, BrainFact>
     */
    public function facts(Project $project): Collection
    {
        return BrainFact::query()
            ->where('project_id', $project->id)
            ->active()
            ->orderBy('observed_at')
            ->get()
            ->keyBy('key');
    }

    public function fact(Project $project, string $key): ?BrainFact
    {
        return BrainFact::query()
            ->where('project_id', $project->id)
            ->where('key', $key)
            ->active()
            ->orderByDesc('observed_at')
            ->first();
    }

    /**
     * قيمة حقيقة، أو البديل إن لم تُعرف.
     *
     * لا يُخفي البديلُ غيابَ المعلومة: من يحتاج التمييز بين «صفر» و«لا نعرف»
     * يستدعي fact() ويفحص null. تعبئة الفراغ صامتًا تخالف §٤.٣.
     */
    public function value(Project $project, string $key, mixed $default = null): mixed
    {
        $fact = $this->fact($project, $key);

        return $fact === null ? $default : ($fact->value_json['value'] ?? $default);
    }

    /**
     * تاريخ مفتاح واحد، من الأقدم إلى الأحدث.
     *
     * @return Collection<int, BrainFact>
     */
    public function history(Project $project, string $key): Collection
    {
        return BrainFact::query()
            ->where('project_id', $project->id)
            ->where('key', $key)
            ->orderBy('observed_at')
            ->get();
    }

    /**
     * التعارضات المفتوحة — ما يحتاج قرار المستخدم لا قرارنا.
     *
     * @return Collection<int, BrainEvent>
     */
    public function openConflicts(Project $project): Collection
    {
        return BrainEvent::query()
            ->where('project_id', $project->id)
            ->where('type', BrainEvent::TYPE_FACT_CONFLICT)
            ->whereNull('outcome')
            ->orderByDesc('occurred_at')
            ->get();
    }

    /**
     * التغطية: كم من المفاتيح المطلوبة يعرفها الدماغ فعلًا.
     *
     * تُستخدم في axis_coverage. الغياب يُعلَن ولا يُملأ بتقدير (§٤.٣).
     *
     * @param  array<int, string>  $requiredKeys
     * @return array{known: int, required: int, ratio: float, missing: array<int, string>}
     */
    public function coverage(Project $project, array $requiredKeys): array
    {
        $required = array_values(array_unique($requiredKeys));

        if ($required === []) {
            return ['known' => 0, 'required' => 0, 'ratio' => 0.0, 'missing' => []];
        }

        $known = $this->facts($project)->keys()->all();
        $missing = array_values(array_diff($required, $known));
        $knownCount = count($required) - count($missing);

        return [
            'known' => $knownCount,
            'required' => count($required),
            'ratio' => round($knownCount / count($required), 4),
            'missing' => $missing,
        ];
    }

    /**
     * أضعف مستوى دليل بين مفاتيح — مستوى أي مخرج مبنيّ عليها.
     *
     * @param  array<int, string>  $keys
     */
    public function evidenceLevelFor(Project $project, array $keys): EvidenceLevel
    {
        $facts = $this->facts($project);

        $levels = [];
        foreach ($keys as $key) {
            $fact = $facts->get($key);

            // مفتاح غائب لا يرفع المستوى: غيابه نفسه يجعل المخرج فرضية.
            $levels[] = $fact?->evidence_level ?? EvidenceLevel::Inferred;
        }

        return EvidenceLevel::weakest($levels);
    }

    /**
     * تجميد الحالة الراهنة لإعادة إنتاج الدرجة لاحقًا.
     */
    public function takeSnapshot(Project $project): BrainSnapshot
    {
        $payload = $this->facts($project)
            ->map(fn (BrainFact $fact) => [
                'value' => $fact->value_json['value'] ?? null,
                'evidence_level' => $fact->evidence_level->value,
                'source_module' => $fact->source_module,
                'observed_at' => $fact->observed_at?->toIso8601String(),
            ])
            ->all();

        return BrainSnapshot::create([
            'project_id' => $project->id,
            'taken_at' => now(),
            'payload' => $payload,
            'schema_version' => BrainSnapshot::CURRENT_SCHEMA_VERSION,
        ]);
    }
}
