<?php

namespace App\Modules\Brain;

use App\Models\Project;
use App\Models\ProjectAnswer;
use App\Modules\Shared\Evidence\EvidenceLevel;
use App\Modules\Shared\Evidence\EvidenceMapper;
use Illuminate\Support\Facades\DB;

/**
 * بوابة المعرفة عن المشروع: تكتب السجل في الدماغ وتُحدِّث إسقاط الحالة.
 *
 * طبقتان لكل معلومة:
 *   - `brain_facts`   السجل التاريخي: يتراكم ولا يُحذف منه شيء (§٩).
 *   - `project_answers` إسقاط الحالة الراهنة: صف واحد لكل حقل، يقرأه عشرات
 *                       المواضع فيبقى سريعًا ومباشرًا.
 *
 * قبل هذا التحويل كان السجل في `project_knowledge_sources`، فصار مصدر حقيقة
 * ثانيًا بجانب الدماغ. الجدول أُسقط ووظيفته انتقلت هنا كاملة.
 *
 * فرق سلوكي مقصود بين الطبقتين: عند تعارض مصدرين يحفظ الدماغ الروايتين
 * ويُعلّم التعارض للمراجعة، بينما يعرض الإسقاط الأحدث. هذا ليس تناقضًا — الشاشة
 * تحتاج قيمة واحدة، والمراجعة تحتاج القصة كاملة.
 */
class ProjectKnowledgeService
{
    public function __construct(
        private readonly BrainWriter $brain,
        private readonly EvidenceMapper $evidence,
    ) {}

    /**
     * تسجيل معلومة.
     *
     * @param  array<string,mixed>  $metadata
     */
    public function record(
        Project $project,
        string $fieldKey,
        mixed $value,
        string $sourceType,
        ?string $sourceKey = null,
        ?int $sourceId = null,
        string $confidence = 'medium',
        ?string $period = null,
        array $metadata = [],
    ): ?ProjectAnswer {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        return DB::transaction(function () use ($project, $fieldKey, $value, $sourceType, $sourceKey, $sourceId, $confidence, $period, $metadata): ProjectAnswer {
            $answer = ProjectAnswer::updateOrCreate(
                ['project_id' => $project->id, 'field_key' => $fieldKey],
                [
                    'value_json' => ['value' => $value],
                    'source_tool_key' => $sourceType === 'consultation' ? 'consultation' : ($sourceType === 'tool' ? $sourceKey : null),
                    'source_run_id' => $sourceType === 'tool' ? $sourceId : null,
                ],
            );

            $this->brain->record(
                project: $project,
                key: $fieldKey,
                value: $value,
                level: $this->levelFor($confidence),
                sourceModule: $this->moduleFor($sourceType),
                sourceReference: $this->referenceFor($sourceType, $sourceKey, $sourceId),
                period: $period,
                metadata: $metadata === [] ? null : $metadata,
            );

            return $answer;
        });
    }

    /**
     * سحب معلومة: يُحذف الإسقاط ويبقى السجل.
     *
     * @param  array<string,mixed>  $metadata
     */
    public function retract(
        Project $project,
        string $fieldKey,
        string $sourceType,
        ?string $sourceKey = null,
        ?int $sourceId = null,
        array $metadata = [],
    ): void {
        DB::transaction(function () use ($project, $fieldKey, $sourceType, $sourceKey, $sourceId, $metadata): void {
            ProjectAnswer::where('project_id', $project->id)
                ->where('field_key', $fieldKey)
                ->delete();

            $this->brain->retract(
                project: $project,
                key: $fieldKey,
                sourceModule: $this->moduleFor($sourceType),
                metadata: [
                    'source_type' => $sourceType,
                    'source_reference' => $this->referenceFor($sourceType, $sourceKey, $sourceId),
                ] + $metadata,
            );
        });
    }

    /**
     * مستوى الدليل من مفردة الثقة القديمة.
     *
     * لا يعتمد على نوع المصدر عمدًا: كل ما يمرّ من هنا أصله المستخدم أو أداة
     * تعمل على كلامه، فلا يبلغ `measured` أبدًا (§١٥). القياس يأتي من الجامعات
     * التي ترى مصدرًا مستقلًا عنه — التدقيق التقني وسجلات الزحف — وتلك تكتب في
     * الدماغ مباشرة بلا وساطة هذه الطبقة.
     */
    private function levelFor(string $confidence): EvidenceLevel
    {
        return $this->evidence->map($confidence);
    }

    private function moduleFor(string $sourceType): string
    {
        return match ($sourceType) {
            'consultation' => 'Intake',
            'tool' => 'PlatformBridge',
            default => 'Profile',
        };
    }

    private function referenceFor(string $sourceType, ?string $sourceKey, ?int $sourceId): ?string
    {
        $parts = array_filter([$sourceType, $sourceKey, $sourceId === null ? null : (string) $sourceId]);

        return $parts === [] ? null : implode(':', $parts);
    }
}
