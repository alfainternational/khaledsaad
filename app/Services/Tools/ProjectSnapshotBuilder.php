<?php

namespace App\Services\Tools;

use App\Models\ToolRun;
use App\Modules\Intake\ConsultationContextBuilder;
use App\Modules\PlatformBridge\LegacyCapabilities;
use App\Modules\Reporting\CrossToolSynthesis;

/**
 * BR-005 / BR-006: التقرير يُبنى على لقطة مجمدة، فتعديل ملف المشروع لاحقًا
 * لا يغير أي تقرير سابق، وتبقى المقارنة بين تقريرين عادلة.
 */
class ProjectSnapshotBuilder
{
    public function __construct(
        private readonly ConsultationContextBuilder $consultations,
        private readonly CrossToolSynthesis $crossTool,
        private readonly LegacyCapabilities $bridge,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(ToolRun $run): array
    {
        $project = $run->project->loadMissing(['profile', 'audiences', 'competitors']);
        $version = $run->toolVersion->loadMissing('tool');

        $snapshot = [
            'captured_at' => now()->toIso8601String(),
            'tool' => [
                'key' => $version->tool->key,
                'name' => $version->tool->name,
                'title' => $version->tool->title,
                'version' => $version->version,
            ],
            'project' => [
                'name' => $project->name,
                'industry' => $project->industry,
                'stage' => $project->stage,
            ],
            'profile' => $project->profile?->only([
                'business_model', 'description', 'geography', 'website',
                'monthly_budget', 'primary_goal', 'value_proposition', 'channels',
            ]) ?? [],
            'audiences' => $project->audiences
                ->map(fn ($audience) => $audience->only(['name', 'pains', 'gains', 'behaviors']))
                ->all(),
            'competitors' => $project->competitors
                ->map(fn ($competitor) => $competitor->only(['name', 'url', 'strengths', 'weaknesses']))
                ->all(),
            'answers' => $this->answers($run),
            'attachments' => $run->files
                ->where('extraction_status', 'completed')
                ->map(fn ($file) => [
                    'name' => $file->original_name,
                    'text' => mb_substr((string) $file->extracted_text, 0, 6000),
                ])
                ->values()
                ->all(),
            'prior_diagnostic_results' => $this->crossTool->priorResults($run),
        ];

        /*
         * لقطة التشخيص تدخل هنا لا في قالب الخطة: هذا ما يجعل ما يُولَّد مبنيًّا
         * على **قياس** النشاط لا على وصف كتبه صاحبه عن نفسه — وهي القيمة
         * الوحيدة للتوليد، لأن التوليد نفسه متاح مجانًا (§٢ و§٨).
         *
         * تمرّ من `PlatformBridge` وحده. الحدّ هنا يحرس قاعدة منتج لا حدًّا
         * شبكيًّا: أن تبقى الخطة نتيجة تابعة للتشخيص، لا مقترح قيمة مستقلًّا.
         */
        if ($this->bridge->hasDiagnosisFor($project)) {
            $snapshot['diagnosis'] = $this->bridge->diagnosisSnapshotFor($project);
        }

        if ($run->consultation_session_id !== null) {
            $snapshot['consultation'] = $this->consultations->build(
                $run->consultationSession()->firstOrFail(),
            );
        }

        return $snapshot;
    }

    /**
     * الإجابات مع مصدرها، حتى يعرف النموذج ما أدخله المستخدم فعلًا
     * وما استُخرج آليًا — الفرق يحدد ما إذا كانت النتيجة دليلًا أم افتراضًا.
     *
     * @return array<int, array<string, mixed>>
     */
    private function answers(ToolRun $run): array
    {
        $labels = $run->toolVersion->fields->pluck('label', 'key');

        return $run->answers
            ->map(fn ($answer) => [
                'key' => $answer->field_key,
                'label' => $labels[$answer->field_key] ?? $answer->field_key,
                'value' => $answer->value_json,
                'source' => $answer->source,
            ])
            ->values()
            ->all();
    }
}
