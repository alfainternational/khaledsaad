<?php

namespace App\Services\Tools;

use App\Jobs\RunToolPipeline;
use App\Models\Project;
use App\Models\Recommendation;
use App\Models\Report;
use App\Models\Task;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\ToolRunAnswer;
use App\Models\User;
use App\Exceptions\BillingLimitException;
use App\Services\Billing\CreditManager;
use App\Services\Billing\Entitlements;
use App\Support\Billing\FeatureKey;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * الطبقة المشتركة بين الويب والـAPI.
 *
 * الغرض منها أن يكون التطبيق والموقع نسختين من منتج واحد لا تنفيذين
 * متوازيين: أي قاعدة عمل تُكتب هنا مرة واحدة فتظهر في الاثنين.
 */
class ToolRunService
{
    /**
     * دفعة التشغيل الجارية، إن كنا داخل تشخيص شامل.
     */
    private ?PendingBatch $batch = null;

    public function __construct(
        private readonly AnswerCompleteness $completeness,
        private readonly ProjectAnswerMemory $memory,
        private readonly CreditManager $credits,
    ) {}

    /**
     * توجيه مهام التشغيل إلى دفعة واحدة بدل إرسالها فرادى.
     */
    public function collectInto(?PendingBatch $batch): void
    {
        $this->batch = $batch;
    }

    public function start(Project $project, Tool $tool, ?User $user, ?int $guestSessionId = null, bool $fresh = false): ToolRun
    {
        if (! $tool->isRunnable()) {
            throw new RuntimeException("الأداة {$tool->title} غير متاحة للتشغيل حاليًا.");
        }

        $existing = $project->runs()
            ->where('tool_version_id', $tool->current_version_id)
            ->where('status', ToolRun::STATUS_DRAFT)
            ->latest('id')
            ->first();

        // الاستئناف هو السلوك الافتراضي حتى لا يضيع ما أدخله المستخدم.
        // «ابدأ من جديد» طلب صريح، فيجب أن يبدأ فعلًا من جديد لا أن يعيده لمسودته.
        if ($existing !== null && ! $fresh) {
            return $existing;
        }

        $existing?->delete();

        $run = $project->runs()->create([
            'tool_version_id' => $tool->current_version_id,
            'user_id' => $user?->id,
            'guest_session_id' => $guestSessionId,
            'status' => ToolRun::STATUS_DRAFT,
            'current_step' => 1,
        ]);

        // ما أجاب عنه المستخدم في أي أداة سابقة يُملأ هنا مسبقًا:
        // لا يُسأل عن الشيء نفسه مرتين مهما تعددت الأدوات.
        $this->prefillFromProfile($run);
        $this->memory->prefill($run);

        return $run;
    }

    /**
     * حفظ خطوة واحدة. الحفظ تدريجي حتى لا يفقد المستخدم شيئًا عند انقطاع الشبكة.
     *
     * @param  array<string, mixed>  $input
     */
    public function saveStep(ToolRun $run, int $step, array $input): ToolRun
    {
        $run->loadMissing(['toolVersion.fields', 'answers']);

        $answers = $this->completeness->contextualAnswers($run);
        $fields = $this->completeness->visibleFields($run->toolVersion, array_merge($answers, $input))
            ->where('step', $step);

        if ($fields->isEmpty()) {
            throw ValidationException::withMessages(['step' => 'خطوة غير معروفة في هذه الأداة.']);
        }

        $rules = [];
        $attributes = [];

        foreach ($fields as $field) {
            $rules[$field->key] = $field->validationRules();
            $attributes[$field->key] = $field->label;
        }

        $validated = validator($input, $rules, [], $attributes)->validate();

        DB::transaction(function () use ($run, $validated, $step): void {
            foreach ($validated as $key => $value) {
                ToolRunAnswer::updateOrCreate(
                    ['tool_run_id' => $run->id, 'field_key' => $key],
                    ['value_json' => ['value' => $value], 'source' => ToolRunAnswer::SOURCE_USER],
                );
            }

            $run->forceFill([
                'current_step' => max($run->current_step, $step + 1),
            ])->save();
        });

        // ما كُتب هنا يصبح معلومًا للمشروع كله: يظهر في ملفه ولا تسأله أداة أخرى عنه.
        $this->memory->remember($run, $validated);

        return $run->refresh()->load('answers');
    }

    /**
     * @return array{missing: array<int, string>, percent: int, assumptions: array<int, string>}
     */
    public function preflight(ToolRun $run): array
    {
        $run->loadMissing(['toolVersion.fields', 'answers', 'files']);

        $unsupported = $run->files
            ->where('extraction_status', 'unsupported')
            ->map(fn ($file) => "الملف {$file->original_name} لن يُقرأ آليًا في هذا الإصدار.")
            ->values()
            ->all();

        return [
            'missing' => $this->completeness->missingRequired($run),
            'percent' => $this->completeness->percent($run),
            'assumptions' => $unsupported,
        ];
    }

    /**
     * @param  bool  $allowIncomplete  التشخيص الشامل يشغّل بما هو معروف ويُعلن
     *                                 فراغاته في التقرير. الاستثناء صريح هنا
     *                                 كي يبقى المعالج اليدوي محميًا كما هو.
     */
    public function queue(ToolRun $run, bool $allowIncomplete = false): ToolRun
    {
        $missing = $this->completeness->missingRequired($run->loadMissing(['toolVersion.fields', 'answers']));

        if ($missing !== [] && ! $allowIncomplete) {
            throw ValidationException::withMessages([
                'answers' => 'أكمل الحقول التالية أولًا: '.implode('، ', $missing),
            ]);
        }

        // حصة التشغيل الشهرية عنصر ميزة مستقل عن الرصيد: الرصيد يقيس التكلفة،
        // والحصة تقيس ما تسمح به الخطة. يُفحص قبل الحجز حتى لا يُحجز ثم يُرفض.
        $this->assertWithinMonthlyQuota($run);

        // BR-004: يُحجز الرصيد عند بدء التشغيل. رصيد غير كافٍ يوقف الطابور
        // قبل أي استدعاء مدفوع، برسالة واضحة لا خطأ تقني.
        $this->credits->hold($run, $run->toolVersion->credit_cost);

        ToolRunPipeline::seedStages($run);

        // العلَم يُثبَّت على التشغيل ليقرأه خط الأنابيب وقت التنفيذ: التشخيص
        // الشامل يُكمل بفجوات معلنة، والتشغيل المستقل يبقى صارمًا كما كان.
        $run->forceFill([
            'status' => ToolRun::STATUS_QUEUED,
            'allow_incomplete' => $allowIncomplete,
            'failure_reason' => null,
        ])->save();

        $job = new RunToolPipeline($run->id);

        // داخل تشخيص شامل تُضاف المهمة إلى دفعته ليُعرف متى انتهت كلها،
        // وخارجه تُرسَل وحدها كما كانت.
        if ($this->batch !== null) {
            $this->batch->add([$job]);
        } else {
            dispatch($job);
        }

        return $run->refresh();
    }

    /**
     * حصة الشهر الجاري: كل تشغيل غادر المسودة يُحسب مرة، والإعادة على تشغيل
     * قائم لا تُحسب مرتين لأنها لا تنشئ صفًّا جديدًا.
     */
    private function assertWithinMonthlyQuota(ToolRun $run): void
    {
        $workspace = $run->project?->workspace;

        if ($workspace === null) {
            return; // تجربة الزائر بلا مساحة عمل: تحكمها حدود أخرى.
        }

        $limit = app(Entitlements::class)->limit($workspace, FeatureKey::TOOL_RUNS_MONTHLY);

        if ($limit === null) {
            return;
        }

        $used = ToolRun::query()
            ->whereHas('project', fn ($query) => $query->where('workspace_id', $workspace->id))
            ->where('status', '!=', ToolRun::STATUS_DRAFT)
            ->where('id', '!=', $run->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        if ($used >= $limit) {
            throw BillingLimitException::quota($limit);
        }
    }

    public function retry(ToolRun $run): ToolRun
    {
        // التشغيل العالق ليس منتهيًا، لكنه لن يتحرك من تلقاء نفسه.
        // منع إعادته كان يترك المستخدم بزر لا يفعل شيئًا.
        if (! $run->isTerminal() && ! $run->isStale()) {
            return $run;
        }

        $run->stages()->update(['status' => 'pending', 'error' => null, 'started_at' => null, 'completed_at' => null]);

        return $this->queue($run);
    }

    /**
     * تحويل توصية إلى مهمة — الخطوة التي تنقل المستخدم من «قرأت» إلى «أنفذ».
     */
    public function convertRecommendation(Recommendation $recommendation, ?User $owner = null): Task
    {
        return Task::firstOrCreate(
            ['recommendation_id' => $recommendation->id],
            [
                'project_id' => $recommendation->report->project_id,
                'owner_id' => $owner?->id,
                'title' => $recommendation->title,
                'description' => $recommendation->description,
                'status' => Task::STATUS_TODO,
                'priority' => $recommendation->priority,
                'impact' => $recommendation->impact,
                'effort' => $recommendation->effort,
                'due_date' => now()->addDays($this->dueInDays($recommendation->effort)),
            ],
        );
    }

    /**
     * @return array<int, Task>
     */
    public function convertTopRecommendations(Report $report, ?User $owner, int $limit = 3): array
    {
        return $report->recommendations()
            ->limit($limit)
            ->get()
            ->map(fn ($recommendation) => $this->convertRecommendation($recommendation, $owner))
            ->all();
    }

    private function dueInDays(string $effort): int
    {
        return match ($effort) {
            'low' => 7,
            'high' => 45,
            default => 21,
        };
    }

    private function prefillFromProfile(ToolRun $run): void
    {
        $profile = $run->project->profile;

        if ($profile === null) {
            return;
        }

        foreach ($run->toolVersion->fields as $field) {
            if ($field->profile_key === null) {
                continue;
            }

            $value = $profile->{$field->profile_key} ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            ToolRunAnswer::updateOrCreate(
                ['tool_run_id' => $run->id, 'field_key' => $field->key],
                ['value_json' => ['value' => $value], 'source' => ToolRunAnswer::SOURCE_PROFILE],
            );
        }
    }
}
