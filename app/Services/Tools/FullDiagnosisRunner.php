<?php

namespace App\Services\Tools;

use App\Jobs\FinishFullDiagnosis;
use App\Models\Project;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * التشخيص الشامل: أمر واحد يشغّل الأدوات كلها ثم يُخرج مستندًا موحدًا.
 *
 * القاعدة المعتمدة صراحةً: نشغّل بما تعرفه المنصة اليوم، ولا نمنع التشغيل
 * بسبب سؤال غير مُجاب. الفراغ لا يُخفى — يُوسم في كل قسم بنسبة اكتماله،
 * ويُعلن قبل التشغيل إن كان كبيرًا. المنع كان سيترك صاحب المشروع بلا شيء،
 * والصمت كان سيعطيه تقريرًا يبدو كاملًا وهو ليس كذلك.
 */
class FullDiagnosisRunner
{
    public const MODE_AUTO = 'auto';

    public const MODE_MANUAL = 'manual';

    /** تحت هذه النسبة من الأسئلة الإلزامية يُحذَّر صاحب المشروع قبل التشغيل. */
    private const WARNING_THRESHOLD = 50;

    public function __construct(
        private readonly ToolRunService $runs,
        private readonly ProjectAnswerMemory $memory,
        private readonly AnswerCompleteness $completeness,
        private readonly ManualReportService $manual,
    ) {}

    /**
     * ما الذي سيحدث لو ضغط الآن — قبل أن يضغط.
     *
     * @return array<string, mixed>
     */
    public function preview(Project $project): array
    {
        $tools = $this->tools();
        $rows = [];
        $answered = 0;
        $required = 0;

        foreach ($tools as $tool) {
            $coverage = $this->coverage($project, $tool);
            $answered += $coverage['answered'];
            $required += $coverage['required'];

            $rows[] = [
                'key' => $tool->key,
                'title' => $tool->title,
                'percent' => $coverage['percent'],
                'missing' => $coverage['missing'],
                'answered' => $coverage['answered'],
                'required' => $coverage['required'],
            ];
        }

        $percent = $required === 0 ? 100 : (int) round($answered / $required * 100);

        return [
            'tools' => $rows,
            'tool_count' => count($rows),
            'coverage_percent' => $percent,
            'needs_warning' => $percent < self::WARNING_THRESHOLD,
            'warning' => $percent < self::WARNING_THRESHOLD
                ? "المنصة تعرف إجابات {$percent}٪ فقط من الأسئلة الإلزامية. سيصلك تقرير كامل البنية، لكن أقسامًا منه ستقوم على افتراضات مُعلنة بدل بيانات."
                : null,
            'last_run_at' => $project->runs()->max('created_at'),
        ];
    }

    /**
     * تشغيل الأدوات كلها. يعيد ما شُغّل وما تعذّر ولماذا — لا ينهار عند أول
     * أداة تفشل، لأن فشل واحدة لا يعني ضياع العشر الأخريات.
     *
     * @return array<string, mixed>
     */
    public function run(Project $project, User $user, string $mode = self::MODE_AUTO, ?int $consultationSessionId = null): array
    {
        $mode = $mode === self::MODE_MANUAL ? self::MODE_MANUAL : self::MODE_AUTO;
        $tools = $this->tools();
        $started = [];
        $skipped = [];

        $batch = $mode === self::MODE_AUTO
            ? Bus::batch([])
                ->name("full-diagnosis:{$project->id}")
                ->allowFailures()
                ->then(fn () => FinishFullDiagnosis::dispatch($project->id, $user->id, $consultationSessionId))
            : null;

        $this->runs->collectInto($batch);

        try {
            foreach ($tools as $tool) {
                try {
                    $run = $this->runs->start($project, $tool, $user);
                    if ($consultationSessionId !== null) {
                        $run->forceFill(['consultation_session_id' => $consultationSessionId])->save();
                    }
                    $this->memory->prefill($run);

                    if ($mode === self::MODE_MANUAL) {
                        $this->manual->requestManualReview($run->refresh(), allowIncomplete: true);
                    } else {
                        $this->runs->queue($run->refresh(), allowIncomplete: true);
                    }

                    $started[] = ['key' => $tool->key, 'title' => $tool->title];
                } catch (Throwable $exception) {
                    // الحصة أو الرصيد أو أداة معطوبة: تُسجَّل بسببها وتُكمل البقية.
                    $skipped[] = [
                        'key' => $tool->key,
                        'title' => $tool->title,
                        'reason' => $exception->getMessage(),
                    ];

                    Log::warning('تعذر تشغيل أداة ضمن التشخيص الشامل', [
                        'project' => $project->id,
                        'tool' => $tool->key,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }
        } finally {
            $this->runs->collectInto(null);
        }

        if ($batch !== null && $started !== []) {
            $batch->dispatch();
        }

        return [
            'mode' => $mode,
            'started' => $started,
            'skipped' => $skipped,
            'started_count' => count($started),
            'skipped_count' => count($skipped),
            'message' => $this->message($mode, count($started), count($skipped)),
        ];
    }

    /**
     * @return Collection<int, Tool>
     */
    private function tools()
    {
        return Tool::runnable()->orderBy('sort_order')->get();
    }

    /**
     * تغطية أداة: كم من أسئلتها الإلزامية المنطبقة تعرف المنصة إجابته.
     *
     * تُحسب على تشغيل مؤقت غير محفوظ حتى لا ينشئ الاستعراض مسودّات.
     *
     * @return array<string, mixed>
     */
    private function coverage(Project $project, Tool $tool): array
    {
        $probe = new ToolRun([
            'project_id' => $project->id,
            'tool_version_id' => $tool->current_version_id,
        ]);
        $probe->setRelation('project', $project);
        $probe->setRelation('toolVersion', $tool->currentVersion);
        $probe->setRelation('answers', collect());

        $known = $this->memory->knownValues($project);
        $context = $this->completeness->contextualAnswers($probe);
        $visible = $this->completeness->visibleFields($tool->currentVersion, array_merge($known, $context));
        $required = $visible->where('required', true);

        $missing = $required
            ->reject(fn ($field) => filled($known[$field->key] ?? null))
            ->pluck('label')
            ->values()
            ->all();

        $answeredCount = $required->count() - count($missing);

        return [
            'required' => $required->count(),
            'answered' => $answeredCount,
            'missing' => array_slice($missing, 0, 4),
            'percent' => $required->isEmpty()
                ? 100
                : (int) round($answeredCount / $required->count() * 100),
        ];
    }

    private function message(string $mode, int $started, int $skipped): string
    {
        if ($started === 0) {
            return 'لم تُشغَّل أي أداة. راجع الأسباب المذكورة بجانب كل أداة.';
        }

        $tail = $skipped > 0 ? " وتعذّر تشغيل {$skipped}." : '';

        return $mode === self::MODE_MANUAL
            ? "أُرسلت {$started} أدوات للمراجعة البشرية. يصلك إشعار عند اكتمال المستند.{$tail}"
            : "بدأ تشغيل {$started} أدوات. يُبنى المستند الموحّد تلقائيًا فور اكتمالها.{$tail}";
    }
}
