<?php

namespace App\Services\Tools;

use App\Models\ProjectAnswer;
use App\Models\ToolRun;
use App\Modules\Diagnosis\DeterministicScorer;
use App\Services\Marketing\BudgetPlanner;
use App\Support\AI\AIRequest;
use App\Support\AI\StructuredRunner;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * مؤشرات المعالج الهجينة: حساب محلي فوري، ثم تفسير مصغر اختياري.
 *
 * لا تحفظ هذه الخدمة الإجابات ولا تغيّر الدرجة أو مسار التشغيل. لذلك يمكن
 * استدعاؤها أثناء الكتابة بأمان، كما يبقى فشل المزود منفصلًا عن حفظ الخطوة.
 */
class HybridInsightService
{
    private const AGENCY_FIELDS = [
        'business_model', 'description', 'geography', 'website',
        'monthly_budget', 'primary_goal', 'value_proposition',
        'audience_clarity', 'active_channels', 'tracking_maturity',
        'competitor_names',
    ];

    public function __construct(
        private readonly AnswerCompleteness $completeness,
        private readonly ProjectContextResolver $context,
        private readonly DeterministicScorer $scorer,
        private readonly DeterministicInsights $insights,
        private readonly StructuredRunner $runner,
    ) {}

    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    public function preview(
        ToolRun $run,
        array $draft = [],
        bool $includeAi = false,
        ?int $step = null,
    ): array {
        $run->loadMissing(['toolVersion.fields', 'toolVersion.tool', 'answers', 'project.profile']);

        $answers = array_merge(
            $this->completeness->plainAnswers($run),
            $this->allowedDraft($run, $draft),
        );
        $contextual = array_merge($answers, $this->context->for($run->project));
        $visible = $this->completeness->visibleFields($run->toolVersion, $contextual);
        $required = $visible->where('required', true);
        $filled = $required->filter(fn ($field) => ! $this->isEmpty($answers[$field->key] ?? null));
        $missing = $required
            ->reject(fn ($field) => ! $this->isEmpty($answers[$field->key] ?? null))
            ->pluck('label')
            ->values()
            ->all();

        $completenessPercent = $required->isEmpty()
            ? 100
            : (int) round($filled->count() / $required->count() * 100);

        $agency = $this->agencyReadiness($run, $answers);
        $signals = $this->signals($run, $answers, $visible->pluck('key')->all());

        return [
            'summary' => [
                'completeness_percent' => $completenessPercent,
                'missing_count' => count($missing),
                'missing' => array_slice($missing, 0, 3),
                'agency_readiness_percent' => $agency['percent'],
                'agency_readiness_label' => $agency['label'],
                'agency_missing' => $agency['missing'],
            ],
            'signals' => array_slice($signals, 0, 2),
            'preliminary' => $includeAi
                ? $this->preliminary($run, $answers, $step)
                : $this->emptyPreliminary('not_requested'),
        ];
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    private function allowedDraft(ToolRun $run, array $draft): array
    {
        $allowed = $run->toolVersion->fields->pluck('key')->flip();

        return collect($draft)
            ->filter(fn ($value, $key) => $allowed->has((string) $key))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $answers
     * @return array{percent: int, label: string, missing: array<int, string>}
     */
    private function agencyReadiness(ToolRun $run, array $answers): array
    {
        $remembered = ProjectAnswer::where('project_id', $run->project_id)
            ->get()
            ->mapWithKeys(fn (ProjectAnswer $answer) => [
                $answer->field_key => $answer->value_json['value'] ?? $answer->value_json,
            ])
            ->all();

        $profile = $run->project->profile?->only(self::AGENCY_FIELDS) ?? [];
        $known = array_merge($profile, $remembered, $answers);
        $missing = collect(self::AGENCY_FIELDS)
            ->filter(fn (string $key) => $this->isEmpty($known[$key] ?? null))
            ->values()
            ->all();
        $percent = (int) round((count(self::AGENCY_FIELDS) - count($missing)) / count(self::AGENCY_FIELDS) * 100);

        return [
            'percent' => $percent,
            'label' => match (true) {
                $percent >= 85 => 'جاهز تسلّمه لأي وكالة بثقة',
                $percent >= 60 => 'قربت تكمل — باقي القليل',
                default => 'ما زال ينقصك أساسيات مهمة',
            },
            'missing' => array_slice($missing, 0, 3),
        ];
    }

    /**
     * @param  array<string, mixed>  $answers
     * @param  array<int, string>  $activeKeys
     * @return array<int, array<string, string>>
     */
    private function signals(ToolRun $run, array $answers, array $activeKeys): array
    {
        $signals = [];
        $budget = is_numeric($answers['monthly_budget'] ?? null)
            ? (float) $answers['monthly_budget']
            : null;
        $cac = is_numeric($answers['known_cac'] ?? null)
            ? (float) $answers['known_cac']
            : null;

        if (($answers['tracking_maturity'] ?? null) === 'none' && $cac !== null && $cac > 0) {
            $signals[] = [
                'type' => 'conflict',
                'title' => 'رقم «كم يكلّفك العميل الواحد» محتاج تتأكد منه',
                'description' => 'أدخلت رقمًا لتكلفة جلب العميل، لكن ليس لديك تتبّع يثبته. استخدمه تقديرًا مؤقتًا حتى تعرف من أين يصل الزائر ومتى يتحول إلى عميل.',
                'basis' => 'قارنّا إجابتين كتبتهما.',
            ];
        }

        if ($budget !== null && $budget > 0 && $cac !== null && $cac > 0) {
            /*
             * الطاقة تُحسب على ما يصل إلى الإعلان فعلًا، لا على المبلغ المعلن.
             * قسمة المبلغ الإجمالي على تكلفة الاستحواذ كانت تَعِد بعملاء
             * يفترضون أن كل ريال يذهب إعلانًا، بينما أتعاب الإدارة والإنتاج
             * قد تبتلع أغلبه — فيخرج المستخدم برقم لا يتحقق ويظنه التزامًا.
             */
            $plan = app(BudgetPlanner::class)->planForProject($run->project);
            $media = $plan['effective_media'];
            $onMedia = $media !== null && $media > 0;
            $basis = $onMedia ? (float) $media : $budget;
            $capacity = (int) floor($basis / $cac);

            $signals[] = [
                'type' => 'calculation',
                'title' => 'كم عميلًا يمكن أن يجلبه لك هذا المبلغ؟',
                'description' => $onMedia && $media < $budget
                    ? 'بعد خصم أتعاب الإدارة والأدوات، يتبقى للإعلان نحو '.number_format($basis)." شهريًا. وفق تكلفة العميل الحالية، يمكن أن يحقق هذا المبلغ نحو {$capacity} عميلًا في الشهر. يعتمد الحساب على المبلغ المخصص للإعلان، لا على كامل الميزانية."
                    : "عند قسمة ميزانيتك على تكلفة العميل الواحد، تكون النتيجة نحو {$capacity} عميلًا في الشهر — قبل أي تحسن في الأداء.",
                'basis' => $onMedia && $media < $budget
                    ? 'حُسبت النتيجة من المبلغ المخصص للإعلان بعد الأتعاب، لا من كامل الميزانية.'
                    : 'حساب من الرقمين اللذين أدخلتهما، وليس توقعًا.',
            ];

            // الرقم المعلن لا يغطي التعاقد أصلًا: تنبيه صريح قبل أي وعد.
            if (($plan['verdict']['level'] ?? null) === BudgetPlanner::VERDICT_INSUFFICIENT) {
                $signals[] = [
                    'type' => 'risk',
                    'title' => $plan['verdict']['headline'],
                    'description' => $plan['verdict']['detail'],
                    'basis' => 'مقارنة ميزانيتك بنطاق الخدمات المطلوب وتكاليف سوقك التقريبية.',
                ];
            }
        } elseif (
            in_array($answers['primary_goal'] ?? null, ['sales', 'leads'], true)
            && ($budget === null || $budget <= 0)
        ) {
            $signals[] = [
                'type' => 'risk',
                'title' => 'هدفك يحتاج من أين يتحرّك',
                'description' => 'تريد مبيعات وعملاء، لكنك لم تحدد ميزانية أو قناة للوصول إلى الجمهور. لا يصبح الهدف قابلًا للتنفيذ حتى تربطه بمصدر واضح للوصول إلى عملائك.',
                'basis' => 'الهدف الذي اخترته مقارنة بميزانيتك الحالية.',
            ];
        }

        if (count($signals) < 2) {
            $baseline = $this->scorer->score($run->toolVersion, $answers, $activeKeys);
            $baseline['breakdown'] = array_values(array_filter(
                $baseline['breakdown'],
                fn (array $row) => ! $this->isEmpty($answers[$row['field']] ?? null),
            ));
            $finding = $this->insights->findings($run, $baseline, 1)[0] ?? null;

            if ($finding !== null) {
                $signals[] = [
                    'type' => 'priority',
                    'title' => $finding['title'],
                    'description' => $finding['recommendations'][0]['description'],
                    'basis' => $finding['evidence'],
                ];
            }
        }

        return $signals;
    }

    /**
     * @param  array<string, mixed>  $answers
     * @return array<string, string>
     */
    private function preliminary(ToolRun $run, array $answers, ?int $step): array
    {
        ksort($answers);
        $key = 'micro-insight:'.$run->id.':'.hash('sha256', json_encode([
            'step' => $step,
            'answers' => $answers,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        try {
            $payload = Cache::remember($key, now()->addMinutes(30), function () use ($run, $answers, $step) {
                return $this->runner->run(AIRequest::json(
                    messages: [
                        [
                            'role' => 'system',
                            'content' => 'أنت تطمئن صاحب مشروع صغير أثناء تعبئته للأداة. استخدم إجاباته فقط، لا تخترع أرقامًا، واكتب عربية بسيطة دافئة بضمير «أنت» كأنك تكلّمه، بلا مصطلحات. هذا مؤشر سريع أثناء التعبئة لا تقرير نهائي.',
                        ],
                        [
                            'role' => 'user',
                            'content' => json_encode([
                                'tool' => $run->toolVersion->tool->title,
                                'completed_step' => $step,
                                'answers' => $answers,
                                'required_output' => 'معنى الإجابات، مخاطرة أو فرصة واحدة، توصية واحدة، وسؤال تعميق اختياري.',
                            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ],
                    ],
                    schema: $this->preliminarySchema(),
                    tier: 'economy',
                    stage: 'micro-insight',
                ), $run);
            });

            return [
                'status' => 'ready',
                'label' => 'مؤشر أولي',
                'meaning' => $payload['meaning'],
                'risk_or_opportunity' => $payload['risk_or_opportunity'],
                'recommendation' => $payload['recommendation'],
                'deepen_question' => $payload['deepen_question'],
            ];
        } catch (Throwable $exception) {
            Log::warning('تعذر التحليل المصغر داخل المعالج', [
                'tool_run_id' => $run->id,
                'reason' => $exception->getMessage(),
            ]);

            return $this->emptyPreliminary('unavailable');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function preliminarySchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['meaning', 'risk_or_opportunity', 'recommendation', 'deepen_question'],
            'properties' => [
                'meaning' => ['type' => 'string'],
                'risk_or_opportunity' => ['type' => 'string'],
                'recommendation' => ['type' => 'string'],
                'deepen_question' => ['type' => 'string'],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function emptyPreliminary(string $status): array
    {
        return [
            'status' => $status,
            'label' => 'مؤشر أولي',
            'meaning' => '',
            'risk_or_opportunity' => '',
            'recommendation' => '',
            'deepen_question' => '',
        ];
    }

    private function isEmpty(mixed $value): bool
    {
        if (is_array($value)) {
            return $value === [];
        }

        return $value === null || trim((string) $value) === '';
    }
}
