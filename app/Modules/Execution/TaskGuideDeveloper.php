<?php

namespace App\Modules\Execution;

use App\Models\AiUsageRecord;
use App\Models\Task;
use App\Modules\Brain\BrainReader;
use App\Modules\Measurement\Models\QueryReservation;
use App\Modules\Measurement\QueryBudgetManager;
use App\Services\Tools\PipelineSchemas;
use App\Support\AI\AIRequest;
use App\Support\AI\StructuredRunner;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * تطوير المهمة: من سطر في لوحة إلى دليل تنفيذ بحالة هذا المشروع.
 *
 * السياق يُقرأ من `Brain` والملف ولا يُسأل عنه المستخدم من جديد (§٩). ولا
 * يخرج المستخدم بيد فارغة مهما حدث: تعذّر المزود يُنتج دليلًا حتميًّا معلَّم
 * المصدر (`guide_status = fallback`)، لا رسالة خطأ.
 *
 * المهمة تبقى حيّة (§٤.٥): إعادة التطوير تكتب فوق الدليل ولا تنشئ مهمة ثانية.
 */
class TaskGuideDeveloper
{
    /**
     * مفاتيح الدماغ التي تفيد التنفيذ. القائمة مقصورة عمدًا: تمرير كل ما
     * يعرفه الدماغ يغرق النموذج ويرفع التكلفة بلا دقة أعلى.
     */
    private const BRAIN_KEYS = [
        'business.value_proposition',
        'business.audience',
        'business.geography',
        'business.monthly_budget',
        'business.primary_goal',
        'business.active_channels',
    ];

    public function __construct(
        private readonly StructuredRunner $runner,
        private readonly BrainReader $brain,
        private readonly DeterministicExampleFactory $examples,
        private readonly QueryBudgetManager $budgets,
    ) {}

    /**
     * @param  QueryReservation|null  $reservation  الحجز المأخوذ **قبل** الطابور
     *                                              (§٤.٤). غيابه يعني أن السقف
     *                                              نفد، فيُكتب دليل حتمي بلا
     *                                              استدعاء واحد.
     */
    public function develop(Task $task, ?QueryReservation $reservation = null): Task
    {
        $task->loadMissing(['project.profile', 'recommendation.report.toolRun']);
        $context = ExampleContext::fromProject($task->project);

        if ($reservation === null) {
            return $this->store($task, $this->fallback($task, $context), Task::GUIDE_FALLBACK);
        }

        /*
         * علامة على سجلات التكلفة قبل الاستدعاء: `StructuredRunner` يكتب
         * `ai_usage_records` ولا يعيد التكلفة لمستدعيه، فالفرق بين ما قبل وما
         * بعد هو تكلفة هذا الاستعلام بمحاولات إعادة التحقق كلها. التسوية
         * بصفر تجعل الحجز رقمًا بلا فاتورة.
         */
        $costFloor = (int) AiUsageRecord::max('id');

        try {
            $guide = $this->runner->run(
                AIRequest::json(
                    messages: $this->messages($task, $context),
                    schema: TaskGuideSchema::schema(),
                    tier: 'standard',
                    stage: 'task_guide',
                    // مثال معطوب من ثلاثة لا يُسقط الدليل كله.
                    salvage: true,
                ),
                $task->recommendation?->report?->toolRun,
            );

            $this->budgets->settle($reservation, costUsd: $this->costSince($costFloor));

            return $this->store($task, $this->normalize($guide, $context), Task::GUIDE_READY);
        } catch (Throwable $exception) {
            // الحجز يُحرَّر بتكلفته الفعلية: المحاولة الفاشلة صرفت فعلًا،
            // لكن المواضع غير المستهلكة تعود للسقف.
            $this->budgets->release($reservation, costUsd: $this->costSince($costFloor));

            // الفشل يُسجَّل ولا يُرمى: المهمة موجودة والمستخدم ينتظر دليلًا،
            // وإرجاعه إلى لوحة فارغة أسوأ من قالب مأمون معلَّم المصدر.
            Log::warning('تعذّر تطوير دليل المهمة بالذكاء الاصطناعي.', [
                'task_id' => $task->id,
                'reason' => $exception->getMessage(),
            ]);

            return $this->store($task, $this->fallback($task, $context), Task::GUIDE_FALLBACK);
        }
    }

    private function costSince(int $floorId): float
    {
        return (float) AiUsageRecord::where('id', '>', $floorId)->sum('cost_usd');
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    private function messages(Task $task, ExampleContext $context): array
    {
        $recommendation = $task->recommendation;

        return [
            ['role' => 'system', 'content' => implode("\n", [
                PipelineSchemas::systemPreamble(null, $task->project?->sector),
                PipelineSchemas::executabilityRubric(),
                TaskGuideSchema::instructions(),
            ])],
            ['role' => 'user', 'content' => "بيانات المهمة والمشروع:\n".json_encode([
                'task' => [
                    'title' => $task->title,
                    'description' => $task->description,
                    'impact' => $task->impact,
                    'effort' => $task->effort,
                    'due_date' => $task->due_date?->toDateString(),
                ],
                'recommendation' => $recommendation === null ? null : [
                    'root_cause' => $recommendation->root_cause,
                    'commercial_impact' => $recommendation->commercial_impact,
                    'action_steps' => $recommendation->action_steps,
                    'timeframe' => $recommendation->timeframe,
                    'kpi_definition' => $recommendation->kpi_definition,
                    'success_condition' => $recommendation->success_condition,
                    'risks' => $recommendation->risks,
                ],
                'project' => [
                    'name' => $context->businessName,
                    'sector' => $context->sector,
                    'audience' => $context->audience,
                    'value_proposition' => $context->valueProposition,
                    'geography' => $context->geography,
                    'primary_goal' => $context->primaryGoal,
                    'website' => $context->website,
                ],
                'brain' => $this->brainFacts($task),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function brainFacts(Task $task): array
    {
        if ($task->project === null) {
            return [];
        }

        $facts = [];

        foreach (self::BRAIN_KEYS as $key) {
            $value = $this->brain->value($task->project, $key);

            if ($value !== null && $value !== '') {
                $facts[$key] = $value;
            }
        }

        return $facts;
    }

    /**
     * تطبيع مخرج النموذج قبل الحفظ: المثال المعطوب يُستبدل ولا يُخزَّن، حتى
     * لا يصل للمستخدم عنوانٌ بلا نصّ يُنسخ.
     *
     * @param  array<string, mixed>  $guide
     * @return array<string, mixed>
     */
    private function normalize(array $guide, ExampleContext $context): array
    {
        $examples = [];

        foreach ($guide['examples'] ?? [] as $payload) {
            $example = WorkedExample::fromPayload($payload);

            if ($example !== null) {
                $examples[] = $example->toArray();
            }
        }

        if ($examples === []) {
            $examples[] = $this->examples
                ->build((string) ($guide['how'] ?? ''), (string) ($guide['deliverable'] ?? ''), $context)
                ->toArray();
        }

        return [
            'how' => (string) ($guide['how'] ?? ''),
            'when' => (string) ($guide['when'] ?? ''),
            'where' => (string) ($guide['where'] ?? ''),
            'deliverable' => (string) ($guide['deliverable'] ?? ''),
            'steps' => $this->strings($guide['steps'] ?? []),
            'checkpoints' => $this->strings($guide['checkpoints'] ?? []),
            'pitfalls' => $this->strings($guide['pitfalls'] ?? []),
            'examples' => $examples,
            'timeframe' => isset($guide['timeframe']) ? (string) $guide['timeframe'] : null,
        ];
    }

    /**
     * الأرضية الحتمية للدليل: تُبنى من التوصية نفسها وحقائق النشاط.
     *
     * تُصرّح بحدودها في `how` بدل أن تتظاهر بالاكتمال — المستخدم يعرف أنه
     * أمام قالب مأمون، ويعرف أن إعادة التطوير متاحة.
     *
     * @return array<string, mixed>
     */
    private function fallback(Task $task, ExampleContext $context): array
    {
        $recommendation = $task->recommendation;
        $steps = $recommendation?->action_steps ?: $this->examples->fallbackSteps();

        $example = WorkedExample::fromStored($recommendation?->worked_example)
            ?? $this->examples->build(
                $task->title,
                (string) $task->description,
                $context,
                $steps,
            );

        /*
         * الملف كله محميّ في `locales.scan.php.never_wrap` لأن أكثره برومبت
         * يُرسَل للنموذج. هذه الأرضية الحتمية وحدها نصّ يقرأه المستخدم من
         * الشاشة، فتُغلَّف يدويًّا هنا ولا يُرفع الحماية عن الملف.
         */
        return [
            'how' => __('هذا دليل مبدئي مبني على التوصية نفسها وما نعرفه عن نشاطك، لا على صياغة مخصصة لحالتك. ابدأ بالخطوات أدناه، ويمكنك طلب تطوير الدليل مرة أخرى في أي وقت.'),
            'when' => $recommendation?->timeframe ?? __('ابدأ هذا الأسبوع، وخصّص لها جلسة واحدة لا تتجاوز الساعة.'),
            'where' => __('في المكان الذي يصل منه عملاؤك اليوم؛ إن لم تكن متأكدًا، ابدأ بالقناة التي جاءك منها آخر عميل.'),
            'deliverable' => $recommendation?->success_condition
                ?? __('شيء واحد ملموس يمكنك أن تريه لغيرك في نهاية الأسبوع.'),
            'steps' => array_values($steps),
            'checkpoints' => [__('راجع بعد ثلاثة أيام: هل أنجزت الخطوة الأولى فعلًا أم ما زالت نية؟')],
            'pitfalls' => array_values($recommendation?->risks ?: [
                'البدء بأكثر من خطوة في وقت واحد يعني عدم إنهاء أيٍّ منها.',
            ]),
            'examples' => [$example->toArray()],
            'timeframe' => $recommendation?->timeframe,
        ];
    }

    /**
     * @param  mixed  $values
     * @return array<int, string>
     */
    private function strings($values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($value) => is_string($value) || is_numeric($value) ? trim((string) $value) : '',
            $values,
        ), fn (string $value) => $value !== ''));
    }

    /**
     * @param  array<string, mixed>  $guide
     */
    private function store(Task $task, array $guide, string $status): Task
    {
        $task->forceFill([
            'guide' => $guide,
            'guide_status' => $status,
            'guide_generated_at' => now(),
            'steps' => $guide['steps'] !== [] ? $guide['steps'] : $task->steps,
            'worked_example' => $guide['examples'][0] ?? $task->worked_example,
            'timeframe' => $guide['timeframe'] ?? $task->timeframe,
        ])->save();

        return $task->refresh();
    }
}
