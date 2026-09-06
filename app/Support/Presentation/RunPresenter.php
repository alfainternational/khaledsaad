<?php

namespace App\Support\Presentation;

use App\Models\ProjectAnswer;
use App\Models\ToolRun;
use App\Services\Tools\AnswerCompleteness;
use App\Services\Tools\HybridInsightService;
use App\Support\Failures\FailureKind;

class RunPresenter
{
    public function __construct(
        private readonly AnswerCompleteness $completeness,
        private readonly ToolPresenter $tools,
        private readonly HybridInsightService $insights,
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
            // العطل المصنَّف: الشاشة تقرأ منه، ولا تعيد تفسير النص بنفسها.
            'failure' => $this->failure($run),
            'is_terminal' => $run->isTerminal(),
            // تشغيل متوقف: الواجهة تقول الحقيقة بدل إبقاء المستخدم ينتظر بلا نهاية.
            'is_stale' => $run->isStale(),
            'stale_hint' => $run->isStale()
                ? __('التحليل لم يتقدم منذ فترة. إجاباتك محفوظة بالكامل — اضغط «أعد المحاولة»، وإن تكرر الأمر فالمعالجة في الخادم متوقفة.')
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
            // الرؤية تُقيَّم بسياق المشروع، بينما تبقى حمولة الإجابات نقية.
            'steps' => $this->tools->steps($run->toolVersion, $this->completeness->contextualAnswers($run), $knownKeys),
            'answers' => $answers,
            'completeness_percent' => $this->completeness->percent($run),
            'insights' => $this->insights->preview($run),
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
                'completed' => __('جاهز للقراءة'),
                'unsupported' => __('لن يُقرأ آليًا'),
                'failed' => __('تعذّرت قراءته'),
                default => __('بانتظار المعالجة'),
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
                    'running' => __('جارية'),
                    'completed' => __('اكتملت'),
                    'failed' => __('تعذرت'),
                    default => __('بانتظار الدور'),
                },
                // خطأ المرحلة تفصيل تشغيلي لا يُعرض للمستخدم: نصّه رسالةُ
                // استثناء، وعرضه هو ما جعل شاشة الفشل تتحدث بلغة الكود.
                // الرسالة التي يقرأها المستخدم واحدة، وهي `failure`.
                'has_error' => $stage->error !== null,
            ])->values()->all(),
        ];
    }

    /**
     * العطل بصيغته المعروضة، مبنيًّا من التصنيف المخزَّن.
     *
     * التشغيلات التي فشلت قبل هجرة التصنيف لا تحمل `failure_kind`؛ تُقرأ
     * على أنها عطلنا لا حدَّ المستخدم، لأن افتراض براءته أرخص من اتهامه خطأً.
     *
     * @return array<string, mixed>|null
     */
    private function failure(ToolRun $run): ?array
    {
        if ($run->failure_reason === null && $run->failure_kind === null) {
            return null;
        }

        /*
         * المؤجَّل ليس فاشلًا، ولا يُعرض كذلك.
         *
         * هو ينتظرنا نحن ويُعاد تلقائيًّا، فلا يُطلب من صاحبه شيء ولا
         * يُقال له «تعذّر». الفرق بين «انتظر، نحن نعمل» و«فشل، أعِد
         * المحاولة» هو الفرق بين مستخدم يبقى وآخر يغادر.
         */
        if ($run->isAwaitingCapacity()) {
            return [
                'kind' => FailureKind::Ours->value,
                'code' => $run->failure_code ?? 'provider_unavailable',
                'title' => __('تحليلك في الانتظار — والسبب لدينا لا لديك'),
                'message' => $run->failure_reason
                    ?? __('إجاباتك محفوظة بالكامل، ولم يُخصم من رصيدك شيء. نعيد التشغيل تلقائيًّا فور عودة الخدمة ونُشعرك.'),
                'is_ours' => true,
                'is_waiting' => true,
                'billing_action' => false,
            ];
        }

        $kind = FailureKind::tryFrom((string) $run->failure_kind) ?? FailureKind::Ours;

        return [
            'kind' => $kind->value,
            'code' => $run->failure_code ?? 'run_failed',
            'title' => $this->failureTitle($kind),
            'message' => $run->failure_reason
                ?? __('إجاباتك محفوظة بالكامل، ولم يُخصم من رصيدك شيء. نعيد التشغيل ونُشعرك عند جاهزية تقريرك.'),
            'is_ours' => $kind === FailureKind::Ours,
            'is_waiting' => false,
            // إجراء الفوترة يظهر للحدود التي يملك المستخدم رفعها وحدها.
            'billing_action' => $kind === FailureKind::Theirs,
        ];
    }

    private function failureTitle(FailureKind $kind): string
    {
        return match ($kind) {
            FailureKind::Theirs => __('التشغيل يحتاج رصيدًا أو خطة أعلى'),
            FailureKind::Input => __('نحتاج إكمال بعض الإجابات'),
            FailureKind::Ours => __('تعذّر تشغيل التحليل — والسبب لدينا لا لديك'),
        };
    }

    private function statusLabel(ToolRun $run): string
    {
        return match ($run->status) {
            ToolRun::STATUS_DRAFT => __('مسودة'),
            ToolRun::STATUS_QUEUED => __('في الطابور'),
            ToolRun::STATUS_PROCESSING => __('قيد التحليل'),
            ToolRun::STATUS_COMPLETED => __('مكتمل'),
            ToolRun::STATUS_PARTIAL => __('مكتمل جزئيًا'),
            ToolRun::STATUS_AWAITING_CAPACITY => __('بانتظار عودة الخدمة'),
            default => __('تعذر'),
        };
    }
}
