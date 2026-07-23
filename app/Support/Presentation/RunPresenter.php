<?php

namespace App\Support\Presentation;

use App\Models\ProjectAnswer;
use App\Models\ToolRun;
use App\Services\Tools\AnswerCompleteness;

class RunPresenter
{
    public function __construct(
        private readonly AnswerCompleteness $completeness,
        private readonly ToolPresenter $tools,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(ToolRun $run): array
    {
        $run->loadMissing(['toolVersion.tool', 'project', 'report']);

        return [
            'uuid' => $run->uuid,
            'status' => $run->status,
            'status_label' => $this->statusLabel($run),
            'current_step' => $run->current_step,
            'base_score' => $run->base_score,
            'confidence' => $run->confidence,
            'failure_reason' => $run->failure_reason,
            'is_terminal' => $run->isTerminal(),
            // تشغيل متوقف: الواجهة تقول الحقيقة بدل إبقاء المستخدم ينتظر بلا نهاية.
            'is_stale' => $run->isStale(),
            'stale_hint' => $run->isStale()
                ? 'التحليل لم يتقدم منذ فترة. إجاباتك محفوظة بالكامل — اضغط «أعد المحاولة»، وإن تكرر الأمر فالمعالجة في الخادم متوقفة.'
                : null,
            'progress_percent' => $run->progressPercent(),
            'tool' => $this->tools->card($run->toolVersion->tool),
            'project' => ['name' => $run->project->name, 'slug' => $run->project->slug],
            'report_id' => $run->report?->id,
            'created_at' => $run->created_at?->toIso8601String(),
            'completed_at' => $run->completed_at?->toIso8601String(),
        ];
    }

    /**
     * الحمولة التي تقرأها شاشة التعبئة في الويب والتطبيق معًا.
     *
     * @return array<string, mixed>
     */
    public function wizard(ToolRun $run): array
    {
        $run->loadMissing(['toolVersion.fields', 'answers', 'project.profile']);
        $answers = $this->completeness->plainAnswers($run);

        // أرقام المقارنة تُحسب على ضوء نشاط هذا المشروع تحديدًا.
        $this->tools->withProject($run->project);

        // ما يعرفه المشروع مسبقًا يُعرض مطويًا: المستخدم يرى الأسئلة الجديدة فقط.
        $knownKeys = ProjectAnswer::where('project_id', $run->project_id)
            ->pluck('field_key')
            ->all();

        return [
            ...$this->summary($run),
            'steps' => $this->tools->steps($run->toolVersion, $answers, $knownKeys),
            'answers' => $answers,
            'completeness_percent' => $this->completeness->percent($run),
            'files' => $this->files($run),
        ];
    }

    /**
     * المرفقات المرفوعة لهذا التشغيل، بحالة قراءتها.
     *
     * @return array<int, array<string, mixed>>
     */
    public function files(ToolRun $run): array
    {
        return $run->files()->latest('id')->get()->map(fn ($file) => [
            'id' => $file->id,
            'name' => $file->original_name,
            'size_kb' => (int) round($file->size_bytes / 1024),
            'status' => $file->extraction_status,
            'status_label' => match ($file->extraction_status) {
                'completed' => 'جاهز للقراءة',
                'unsupported' => 'لن يُقرأ آليًا',
                'failed' => 'تعذّرت قراءته',
                default => 'بانتظار المعالجة',
            },
        ])->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function progress(ToolRun $run): array
    {
        $run->loadMissing('stages');

        return [
            ...$this->summary($run),
            'stages' => $run->stages->map(fn ($stage) => [
                'key' => $stage->key,
                'label' => $stage->label,
                'status' => $stage->status,
                'status_label' => match ($stage->status) {
                    'running' => 'جارية',
                    'completed' => 'اكتملت',
                    'failed' => 'تعذرت',
                    default => 'بانتظار الدور',
                },
                'error' => $stage->error,
            ])->values()->all(),
        ];
    }

    private function statusLabel(ToolRun $run): string
    {
        return match ($run->status) {
            ToolRun::STATUS_DRAFT => 'مسودة',
            ToolRun::STATUS_QUEUED => 'في الطابور',
            ToolRun::STATUS_PROCESSING => 'قيد التحليل',
            ToolRun::STATUS_COMPLETED => 'مكتمل',
            ToolRun::STATUS_PARTIAL => 'مكتمل جزئيًا',
            default => 'تعذر',
        };
    }
}
