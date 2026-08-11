<?php

namespace App\Services\Tools;

use App\Exceptions\AIInvalidOutputException;
use App\Exceptions\AIProviderException;
use App\Models\ToolRun;
use App\Models\ToolRunStage;
use App\Modules\Alerts\RunNotifier;
use App\Modules\Competitors\CompetitorRegistry;
use App\Modules\Diagnosis\DeterministicScorer;
use App\Modules\Intake\IntakeCollector;
use App\Services\Billing\CreditManager;
use App\Services\Tools\V2\ReportSemanticGuard;
use App\Support\AI\AIRequest;
use App\Support\AI\StructuredRunner;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * خط أنابيب التقرير كما في القسم 17 من وثيقة المنتج.
 *
 * مبدأ الثبات: المراحل الحتمية (اللقطة، الدرجة، الحفظ) لا تعتمد على الذكاء
 * الاصطناعي إطلاقًا. لذلك فشل المزود يُنتج تقريرًا جزئيًا يحتفظ بالدرجة
 * والإجابات، ولا يُفقد المستخدم ما أدخله.
 */
class ToolRunPipeline
{
    /**
     * @var array<int, array{key: string, label: string}>
     */
    public const STAGES = [
        ['key' => 'entitlement', 'label' => 'التحقق من الاستحقاق واكتمال البيانات'],
        ['key' => 'snapshot', 'label' => 'تجميد لقطة المشروع'],
        ['key' => 'extraction', 'label' => 'استخراج محتوى المرفقات'],
        ['key' => 'baseline', 'label' => 'حساب الدرجة الأساسية'],
        ['key' => 'gaps', 'label' => 'اكتشاف النواقص والتعارضات'],
        ['key' => 'sections', 'label' => 'تحليل أقسام التقرير'],
        ['key' => 'consistency', 'label' => 'مراجعة الاتساق والأرقام'],
        ['key' => 'synthesis', 'label' => 'الملخص والنتائج والتوصيات'],
        ['key' => 'persist', 'label' => 'حفظ التقرير وتسجيل التكلفة'],
        ['key' => 'notify', 'label' => 'إتاحة التقرير والتصدير'],
    ];

    public function __construct(
        private readonly StructuredRunner $runner,
        private readonly ProjectSnapshotBuilder $snapshotBuilder,
        private readonly DeterministicScorer $scorer,
        private readonly AttachmentExtractor $extractor,
        private readonly ReportComposer $composer,
        private readonly CreditManager $credits,
        private readonly RunNotifier $notifier,
        private readonly CompetitorRegistry $competitors,
        private readonly ReportSemanticGuard $semanticGuard,
        private readonly IntakeCollector $intake,
        private readonly DeterministicInsights $deterministic,
    ) {}

    public static function seedStages(ToolRun $run): void
    {
        foreach (self::STAGES as $index => $stage) {
            ToolRunStage::updateOrCreate(
                ['tool_run_id' => $run->id, 'key' => $stage['key']],
                ['label' => $stage['label'], 'sort_order' => $index, 'status' => 'pending'],
            );
        }
    }

    public function handle(ToolRun $run): void
    {
        /*
         * لغة التشغيل هي مصدر الحقيقة لا لغة العملية.
         *
         * حمولة الطابور تحمل لغة لحظة الإرسال، وهي تكفي في المسار العادي.
         * لكن إعادة المحاولة اليدوية من لوحة الإدارة تُرسِل المهمة من جلسة
         * مدير قد تكون لغته غير لغة صاحب التشغيل — فيصل صاحبَه تقريرٌ
         * بلغة من أعاد المحاولة. القراءة من السجل تُغلق هذا الباب.
         */
        if ($run->locale !== null && $run->locale !== '') {
            app()->setLocale($run->locale);
        }

        self::seedStages($run);

        $run->forceFill([
            'status' => ToolRun::STATUS_PROCESSING,
            'started_at' => $run->started_at ?? now(),
            'attempts' => $run->attempts + 1,
            'failure_reason' => null,
        ])->save();

        $run->load(['toolVersion.fields', 'toolVersion.prompts', 'toolVersion.tool', 'answers', 'files', 'project.profile']);

        $degraded = false;

        try {
            $this->run($run, 'entitlement', fn () => $this->assertRunnable($run));
            $snapshot = $this->run($run, 'snapshot', fn () => $this->snapshot($run));
            $this->run($run, 'extraction', fn () => $this->extractor->extractAll($run));
            $baseline = $this->run($run, 'baseline', fn () => $this->baseline($run));
            // نلتقط المنافسين المسمّين مبكرًا حتى يراهم تحليل الذكاء الاصطناعي
            // ويقارن العميل بهم بالاسم، لا بكلام عام عن المنافسة.
            $this->captureNamedCompetitors($run);

            /*
             * الدماغ يتغذّى هنا لا بعد النجاح: الإجابات مثبَّتة عند هذه النقطة،
             * وما بعدها استدعاءات نموذج قد تفشل. ربط التغذية بالنجاح الكامل
             * يعني أن عطل مزوّد يمحو تعلّم النظام من إجابات صحيحة أدخلها
             * صاحب النشاط فعلًا.
             */
            if ($run->project !== null) {
                $this->intake->collect($run->project);
            }
        } catch (Throwable $exception) {
            $this->fail($run, $exception);

            return;
        }

        // من هنا فصاعدًا كل فشل يخفض الجودة ولا يلغي التقرير.
        $gaps = $this->attempt($run, 'gaps', fn () => $this->gaps($run, $snapshot), $degraded);
        $sections = $this->attempt($run, 'sections', fn () => $this->sections($run, $snapshot, $gaps), $degraded) ?? [];
        $consistency = $this->attempt($run, 'consistency', fn () => $this->consistency($run, $sections), $degraded);
        $synthesis = $this->attempt($run, 'synthesis', fn () => $this->synthesis($run, $snapshot, $sections, $consistency, $baseline), $degraded);
        if (is_array($synthesis)) {
            $synthesis = $this->semanticGuard->repair($synthesis, [
                'snapshot' => $snapshot,
                'sections' => $sections,
                'consistency' => $consistency,
                'competitors' => $this->competitorContext($run),
            ], $baseline);
        }

        try {
            $this->run($run, 'persist', fn () => $this->composer->compose($run, $baseline, $sections, $synthesis, $gaps));
            $this->run($run, 'notify', fn () => $this->publish($run));
        } catch (Throwable $exception) {
            $this->fail($run, $exception);

            return;
        }

        $run->forceFill([
            'status' => $degraded ? ToolRun::STATUS_PARTIAL : ToolRun::STATUS_COMPLETED,
            'completed_at' => now(),
        ])->save();

        // نجح التشغيل (كليًا أو جزئيًا): التقرير جاهز، فيُثبَّت الخصم ويُشعَر المستخدم.
        // الجزئي ينجح أيضًا لأنه يسلّم درجة ونتائج قابلة للتنفيذ.
        $this->credits->charge($run);
        $this->notifier->reportReady($run->fresh());
    }

    /**
     * مرحلة إلزامية: فشلها يوقف كل شيء.
     */
    private function run(ToolRun $run, string $key, callable $work): mixed
    {
        $stage = $this->stage($run, $key);
        $stage->markRunning();

        $result = $work();

        $stage->markCompleted();

        return $result;
    }

    /**
     * مرحلة اختيارية: فشلها يُسجل ويُخفض حالة التشغيل إلى جزئي.
     */
    private function attempt(ToolRun $run, string $key, callable $work, bool &$degraded): mixed
    {
        $stage = $this->stage($run, $key);
        $stage->markRunning();

        try {
            $result = $work();
            $stage->markCompleted();

            return $result;
        } catch (AIProviderException|AIInvalidOutputException $exception) {
            $degraded = true;

            // المخالفات هي المعلومة الوحيدة القابلة للتصرف. تسجيل الرسالة
            // العامة وحدها كان يجعل كل فشل يبدو متطابقًا وغير قابل للتشخيص.
            $violations = $exception instanceof AIInvalidOutputException
                ? $exception->violations
                : [];

            $stage->markFailed(
                $violations === []
                    ? $exception->getMessage()
                    : $exception->getMessage().' التفاصيل: '.implode(' | ', array_slice($violations, 0, 5)),
            );

            Log::warning('فشل مرحلة ذكاء اصطناعي', [
                'tool_run_id' => $run->id,
                'stage' => $key,
                'reason' => $exception->getMessage(),
                'violations' => $violations,
            ]);

            return null;
        }
    }

    private function stage(ToolRun $run, string $key): ToolRunStage
    {
        return $run->stages()->where('key', $key)->firstOrFail();
    }

    private function assertRunnable(ToolRun $run): void
    {
        $tool = $run->toolVersion->tool;

        if (! $tool->isRunnable()) {
            throw new \RuntimeException("الأداة {$tool->title} غير متاحة للتشغيل حاليًا.");
        }

        $missing = app(AnswerCompleteness::class)->missingRequired($run);

        if ($missing === []) {
            return;
        }

        // التشخيص الشامل يُشغّل «بما هو معروف» (§٤.٣): النقص لا يمنع التشغيل بل
        // يُعلَن فجوةً، والمراحل الحتمية (اللقطة، الدرجة) لا تحتاج اكتمالًا.
        // التشغيل المستقل يبقى صارمًا كي لا يُهدر استدعاء نموذج على بيانات ناقصة.
        if ($run->allow_incomplete) {
            Log::info('تشخيص شامل يُكمل رغم نقص بيانات أداة', [
                'tool_run_id' => $run->id,
                'missing' => $missing,
            ]);

            return;
        }

        throw new \RuntimeException('بيانات ناقصة: '.implode('، ', $missing));
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(ToolRun $run): array
    {
        $snapshot = $this->snapshotBuilder->build($run);
        $run->forceFill(['snapshot' => $snapshot])->save();

        return $snapshot;
    }

    /**
     * @return array{score: int, band: string, breakdown: array<int, array<string, mixed>>}
     */
    private function baseline(ToolRun $run): array
    {
        $answers = collect($run->answerMap())
            ->map(fn ($value) => is_array($value) && array_key_exists('value', $value) ? $value['value'] : $value)
            ->all();

        // الدرجة على الأسئلة المنطبقة على هذا المشروع فقط — عدالة التكيف.
        $completeness = app(AnswerCompleteness::class);
        $activeKeys = $completeness
            ->visibleFields($run->toolVersion, $completeness->contextualAnswers($run))
            ->pluck('key')
            ->all();

        $baseline = $this->scorer->score($run->toolVersion, $answers, $activeKeys);

        $run->forceFill(['base_score' => $baseline['score']])->save();

        // درجة بطاقة المشروع هي المؤشر التسويقي الشامل فقط. درجات الأدوات
        // المتخصصة تخص تقاريرها ولا يجوز أن تستبدل المؤشر العام.
        if ($run->toolVersion->tool->key === 'marketing-score') {
            $run->project->forceFill(['latest_score' => $baseline['score']])->save();
        }

        return $baseline;
    }

    /**
     * يلتقط ما سمّاه المستخدم من منافسين إلى سجلّ المشروع، قبل التحليل.
     */
    private function captureNamedCompetitors(ToolRun $run): void
    {
        $answer = collect($run->answerMap())->get('competitor_names');
        $names = is_array($answer) && array_key_exists('value', $answer) ? $answer['value'] : $answer;

        if (is_string($names) && trim($names) !== '') {
            $this->competitors->rememberNamed($run->project, $names);
        }
    }

    /**
     * منافسو المشروع بالاسم لسياق الخلاصة: مؤكدون (محلي أولًا) ومرشّحون.
     *
     * @return array<string, mixed>
     */
    private function competitorContext(ToolRun $run): array
    {
        $view = $this->competitors->forReport($run->project);

        return [
            'named' => collect($view['confirmed'])->map(fn ($c) => ['name' => $c['name'], 'tier' => $c['tier_label']])->all(),
            'candidates' => collect($view['candidates'])->pluck('name')->all(),
            'note' => 'قارن العميل بمنافسيه المسمّين بالاسم حين يفيد ذلك؛ المرشّحون غير مؤكدين فلا تبنِ عليهم حكمًا قاطعًا.',
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function gaps(ToolRun $run, array $snapshot): array
    {
        return $this->runner->run(AIRequest::json(
            messages: $this->messages($run, 'gaps', [
                'snapshot' => $snapshot,
            ]),
            schema: PipelineSchemas::gaps(),
            tier: 'economy',
            stage: 'gaps',
        ), $run);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>|null  $gaps
     * @return array<int, array<string, mixed>>
     */
    private function sections(ToolRun $run, array $snapshot, ?array $gaps): array
    {
        $sections = [];

        foreach ($run->toolVersion->section_plan as $index => $section) {
            $payload = $this->runner->run(AIRequest::json(
                messages: $this->messages($run, 'section:'.$section['key'], [
                    'snapshot' => $snapshot,
                    'gaps' => $gaps,
                    'section' => $section,
                ]),
                schema: $section['schema'] ?? PipelineSchemas::section(),
                tier: $section['tier'] ?? 'standard',
                stage: 'section:'.$section['key'],
            ), $run);

            $sections[] = [
                'key' => $section['key'],
                'title' => $section['title'],
                'sort_order' => $index,
                'content' => $payload,
            ];
        }

        return $sections;
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<string, mixed>
     */
    private function consistency(ToolRun $run, array $sections): array
    {
        if ($sections === []) {
            return ['issues' => []];
        }

        return $this->runner->run(AIRequest::json(
            messages: $this->messages($run, 'consistency', ['sections' => $sections]),
            schema: PipelineSchemas::consistency(),
            tier: 'economy',
            stage: 'consistency',
        ), $run);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<int, array<string, mixed>>  $sections
     * @param  array<string, mixed>|null  $consistency
     * @param  array<string, mixed>  $baseline
     * @return array<string, mixed>
     */
    private function synthesis(ToolRun $run, array $snapshot, array $sections, ?array $consistency, array $baseline): array
    {
        // أضعف بنود الدرجة أولًا (الأدنى نقاطًا) لتنطلق النتائج منها لا من ترتيب حر.
        $breakdown = collect($baseline['breakdown'] ?? [])->sortBy('points')->values()->all();

        $messages = $this->messages($run, 'synthesis', [
            'snapshot' => $snapshot,
            'sections' => $sections,
            'consistency' => $consistency,
            'baseline' => $baseline,
            'breakdown' => $breakdown,
            // مرساة النصيحة المُنسَّقة لأضعف البنود، من نفس مصدر الأرضية الحتمية.
            'weak_advice' => $this->deterministic->anchors($run, $baseline),
            // منافسو المشروع بالاسم: التحليل يقارن بهم لا بمنافسة مجرّدة.
            'competitors' => $this->competitorContext($run),
        ]);

        // بند التركيب: يوضع أخيرًا حيث انتباه النموذج أقوى.
        $messages[] = [
            'role' => 'user',
            'content' => 'ابدأ من الأضعف: رتّب نتائجك انطلاقًا من أدنى بنود breakdown نقاطًا (الأثقل وزنًا أولًا)، '
                .'لا بترتيبك الحرّ. لكل بند ضعيف مُرِّر إليك ضمن weak_advice، ابنِ توصيتك على مرساته وكيّف صياغتها '
                .'لإجابات العميل تحديدًا، دون أن تناقض اتجاهها. لا تُعِد اختراع النصيحة من الصفر حين تتوفّر مرساة.',
        ];

        return $this->runner->run(AIRequest::json(
            messages: $messages,
            schema: $run->toolVersion->output_schema,
            tier: 'advanced',
            stage: 'synthesis',
            // نُبقي النتائج السليمة إن كانت واحدة معطوبة، بدل إسقاط التقرير كله.
            salvage: true,
        ), $run);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, array{role: string, content: string}>
     */
    private function messages(ToolRun $run, string $stage, array $context): array
    {
        $prompt = $run->toolVersion->promptFor($stage);

        if ($prompt === null) {
            throw new \RuntimeException("لا يوجد برومبت للمرحلة {$stage}.");
        }

        // BR-012: أول استخدام يقفل الإصدار نهائيًا.
        $prompt->lock();

        return [
            // القطاع المعلن يبدّل المثال الذهبي إلى نظيره القطاعي إن أُلِّف.
            ['role' => 'system', 'content' => PipelineSchemas::systemPreamble(
                $run->toolVersion->tool->key,
                $run->project?->sector,
            )],
            ['role' => 'user', 'content' => $prompt->content],
            [
                'role' => 'user',
                'content' => "بيانات التشغيل:\n".json_encode(
                    $context,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
                ),
            ],
        ];
    }

    private function publish(ToolRun $run): void
    {
        $run->report?->forceFill([
            'status' => 'published',
            'published_at' => now(),
        ])->save();
    }

    private function fail(ToolRun $run, Throwable $exception): void
    {
        $this->stage($run, $this->currentStageKey($run))->markFailed($exception->getMessage());

        $run->forceFill([
            'status' => ToolRun::STATUS_FAILED,
            // قصّ دفاعي: حتى مع عمود TEXT لا نخزّن رسالة مرضية الطول، والقصّ
            // يحمي أيضًا أي بيئة لم تُطبَّق فيها هجرة التوسيع بعد.
            'failure_reason' => Str::limit($exception->getMessage(), 2000),
            'completed_at' => now(),
        ])->save();

        // BR-011: الفشل التقني لا يستهلك رصيدًا — يُسترد الحجز كاملًا.
        $this->credits->refund($run);
        $this->notifier->reportFailed($run->fresh());

        Log::error('فشل خط أنابيب التقرير', [
            'tool_run_id' => $run->id,
            'reason' => $exception->getMessage(),
        ]);
    }

    private function currentStageKey(ToolRun $run): string
    {
        return $run->stages()->where('status', 'running')->value('key')
            ?? self::STAGES[0]['key'];
    }
}
