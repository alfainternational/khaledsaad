<?php

namespace App\Services\Tools;

use App\Models\Finding;
use App\Models\Recommendation;
use App\Models\Report;
use App\Models\ToolRun;
use App\Modules\Competitors\CompetitorRegistry;
use App\Modules\Diagnosis\ScoreExplainer;
use App\Modules\Execution\ExampleContext;
use App\Modules\Execution\RecommendationEnricher;
use Illuminate\Support\Facades\DB;

/**
 * تحويل مخرج JSON إلى كيانات مترابطة.
 *
 * هذه هي النقطة التي يتوقف عندها «نص مولد» ويبدأ «منتج»: النتيجة تصبح
 * findings ثم recommendations، والتوصية قابلة للتحول إلى مهمة ومؤشر.
 */
class ReportComposer
{
    public function __construct(
        private readonly DeterministicInsights $deterministic,
        private readonly AdLibraries $adLibraries,
        private readonly CompetitorRegistry $competitors,
        private readonly RecommendationEnricher $enricher,
        private readonly ScoreExplainer $explainer,
    ) {}

    /**
     * @param  array{score: int, band: string, breakdown: array<int, array<string, mixed>>}  $baseline
     * @param  array<int, array<string, mixed>>  $sections
     * @param  array<string, mixed>|null  $synthesis
     * @param  array<string, mixed>|null  $gaps
     */
    public function compose(ToolRun $run, array $baseline, array $sections, ?array $synthesis, ?array $gaps): Report
    {
        return DB::transaction(function () use ($run, $baseline, $sections, $synthesis, $gaps): Report {
            // ما سمّاه المستخدم من منافسين يُخزَّن على مستوى المشروع: محليّون مؤكدون
            // يقودون التحليل، ويظهرون في تقارير الأدوات الأخرى بلا إعادة إدخال.
            $this->captureNamedCompetitors($run);
            $competitorView = $this->competitors->forReport($run->project);

            // أرضية حتمية: حين يفشل الذكاء الاصطناعي، النتائج والتوصيات تُشتق من
            // درجة العميل نفسها فلا يصل تقرير بلا خطوة واحدة قابلة للتنفيذ.
            $findings = $synthesis['findings'] ?? [];

            if ($findings === [] || ! $this->hasRecommendations($findings)) {
                $findings = $this->deterministic->findings($run, $baseline);
                $usedFloor = true;
            } else {
                $usedFloor = false;
            }

            // مراقبة المنافسين تصير خطوة قابلة للتنفيذ: تُضاف في نهاية النتائج
            // فلا تزاحم الأولوية الأعلى، وتتحول بضغطة إلى مهمة.
            $competitorFinding = $this->competitorFinding($run, $competitorView);

            if ($competitorFinding !== null) {
                $findings[] = $competitorFinding;
            }

            $nextStep = $synthesis['next_step']
                ?? $this->nextStepFrom($findings)
                ?? $this->fallbackNextStep();

            $report = Report::updateOrCreate(
                ['tool_run_id' => $run->id],
                [
                    'project_id' => $run->project_id,
                    'title' => $this->title($run),
                    'status' => 'draft',
                    'score' => $baseline['score'],
                    'score_band' => $baseline['band'],
                    'summary' => $synthesis['summary'] ?? $this->fallbackSummary($baseline, $usedFloor),
                    'assumptions' => $this->assumptions($synthesis, $gaps),
                    'next_step' => $nextStep,
                    'generated_by_model' => $run->usageRecords()->latest('id')->value('model'),
                    'tool_version' => $run->toolVersion->version,
                ],
            );

            $report->sections()->delete();
            $report->findings()->delete();

            $this->writeScoreSection($report, $run, $baseline);
            $this->writeSections($report, $sections);
            $this->writeCompetitorSection($report, $run, $competitorView);
            $this->writeFindings($report, $findings, ExampleContext::fromProject($run->project));
            // ضمان حتمي: كل نتيجة افتراضية يظهر أساسها في assumptions، بعد أن
            // يُعرف القسر النهائي لـ is_assumption داخل writeFindings.
            $this->reconcileAssumptions($report);

            $run->forceFill([
                'confidence' => $synthesis['confidence'] ?? $this->inferredConfidence($report),
            ])->save();

            return $report->refresh();
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $findings
     */
    private function hasRecommendations(array $findings): bool
    {
        foreach ($findings as $finding) {
            if (! empty($finding['recommendations'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * الخطوة التالية من أعلى توصية أثرًا، حتى تبقى بطاقة «ابدأ بهذا» مملوءة دائمًا.
     *
     * @param  array<int, array<string, mixed>>  $findings
     * @return array{title: string, description: string}|null
     */
    private function nextStepFrom(array $findings): ?array
    {
        $first = $findings[0]['recommendations'][0] ?? null;

        if ($first === null) {
            return null;
        }

        return [
            'title' => $first['title'],
            'description' => $first['description'],
        ];
    }

    private function title(ToolRun $run): string
    {
        $tool = $run->toolVersion->tool;

        return "{$tool->title} — {$run->project->name}";
    }

    /**
     * @param  array{score: int, band: string, breakdown: array<int, array<string, mixed>>}  $baseline
     */
    private function writeScoreSection(Report $report, ToolRun $run, array $baseline): void
    {
        $explained = $this->explainer->explain($run->toolVersion, $baseline);

        $report->sections()->create([
            'key' => 'score',
            'title' => 'درجتك وسبب كل نقطة فيها',
            'sort_order' => 0,
            'content_json' => [
                'score' => $explained['score'],
                'band' => $explained['band'],
                'breakdown' => $explained['breakdown'],
                'excluded' => $explained['excluded'] ?? [],
                'total_weight' => $explained['total_weight'] ?? 0,
                'method' => 'هذه الدرجة محسوبة من إجاباتك أنت بقواعد ثابتة — نفس الإجابات تعطي نفس الدرجة دائمًا.',
                // أمانة القياس (§٤.١): الأوزان تقدير منهجي لا معايرة على بيانات،
                // وإخفاء ذلك يحوّل فرضية إلى حقيقة في عين القارئ.
                'weights_basis' => 'الأوزان أدناه ترتيب أهمية وضعناه نحن بحكم منهجي، لا معايرة على بيانات حملات. هي تعكس أي بند نراه أخطر على نتيجتك، وتظل قابلة للمراجعة.',
                'weights_scale' => ScoreExplainer::SCALE_NOTE,
            ],
        ]);
    }

    /**
     * قسم مراقبة المنافسين: يبدأ من منافسيه هو لا من فراغ.
     *
     * يجمع طبقتين: المحليون الذين سمّاهم المستخدم (يقين، يقودون)، ومكتبات
     * إعلانات المنصات التي اختارها (أين يرى إعلاناتهم)، ومرشّحون للتأكيد.
     *
     * @param  array{confirmed: array<int, array<string, mixed>>, candidates: array<int, array<string, mixed>>, has_local: bool}  $competitorView
     */
    private function writeCompetitorSection(Report $report, ToolRun $run, array $competitorView): void
    {
        $watchlist = $this->adLibraries->forPlatforms($this->selectedPlatforms($run));

        // بلا منافسين مسمّين وبلا منصات: لا نفرض قسمًا فارغًا.
        if ($competitorView['confirmed'] === [] && $competitorView['candidates'] === [] && $watchlist === []) {
            return;
        }

        $report->sections()->create([
            'key' => 'competitors',
            'title' => 'راقب منافسيك',
            'sort_order' => 60,
            'content_json' => [
                'intro' => 'أنت أعرف بمنافسيك المحليين منّا — هم أقرب خطر عليك وأكثر من يسحب عملاءك. '
                    .'هنا جمعنا من سمّيتهم، وأين ترى إعلانات الجميع، وعلى ماذا تركّز نظرك بالضبط.',
                'confirmed' => $competitorView['confirmed'],
                'candidates' => $competitorView['candidates'],
                // دعوة صريحة حين لا يكون قد سمّى محليًا: هم الأهم.
                'prompt_local' => ! $competitorView['has_local'],
                'watchlist' => $watchlist,
                'look_for' => [
                    'العرض الذي يكررونه، مثل الخصم أو الضمان أو التوصيل المجاني؛ فقد يكون سببًا في جذب عملائهم، ويجب أن تحدد ما يميّز عرضك عنه.',
                    'الإعلان الذي استمر مدة طويلة؛ فقد يدل استمراره على فعاليته، لذلك ادرس فكرته ورسالته.',
                    'شكل الإعلان ونبرته، مثل الفيديو أو الصورة أو رأي العميل، لكي تعرف ما اعتاد جمهورك التفاعل معه.',
                ],
            ],
        ]);
    }

    /**
     * نتيجة قابلة للتحويل إلى مهمة: من مراقبة المنافسين إلى فعل متابَع.
     *
     * @param  array{confirmed: array<int, array<string, mixed>>, has_local: bool}  $competitorView
     * @return array<string, mixed>|null
     */
    private function competitorFinding(ToolRun $run, array $competitorView): ?array
    {
        $hasPlatforms = $this->selectedPlatforms($run) !== [];

        if ($competitorView['confirmed'] === [] && ! $hasPlatforms) {
            return null;
        }

        // إن سمّى محليين: المهمة أن يراقبهم. وإن لم يسمِّ: المهمة أن يحدّدهم أولًا.
        if ($competitorView['has_local']) {
            $names = collect($competitorView['confirmed'])
                ->where('tier', 'local')
                ->pluck('name')
                ->take(3)
                ->implode('، ');

            $title = 'تابع إعلانات منافسيك المحليين';
            $recTitle = "ادرس إعلانات: {$names}";
            $recDescription = 'افتح مكتبات الإعلانات، وابحث عن كل منافس، ثم سجّل العرض الذي يكرره '
                .'وأطول إعلان استمر في الظهور. نفّذ ذلك قبل ضبط رسالتك.';
        } else {
            $title = 'حدّد منافسيك المحليين قبل أي شيء';
            $recTitle = 'اكتب اسمين أو ثلاثة من منافسيك المحليين';
            $recDescription = 'المنافس الذي يجذب عملاءك في مدينتك هو الأجدر بتوجيه خطتك، وليس العلامات الكبيرة البعيدة. '
                .'اكتب اسم الجهة التي يذكرها عميلك عند المقارنة، ومن سبقك إلى عميل كنت تتوقعه — ثم تابع إعلاناتهم.';
        }

        return [
            'title' => $title,
            'description' => 'غالبًا يكون منافسك القريب مصدر الضغط الذي تشعر به في السوق. متابعته بانتظام من أقل طرق بحث السوق تكلفة وأكثرها دقة.',
            'category' => 'المنافسون',
            'severity' => 'medium',
            'is_assumption' => false,
            'evidence' => $competitorView['has_local']
                ? 'مبني على المنافسين الذين ذكرتهم بنفسك.'
                : 'لم تسجّل أسماء منافسين محليين حتى الآن.',
            'confidence' => 85,
            'recommendations' => [[
                'title' => $recTitle,
                'description' => $recDescription,
                'impact' => 'high',
                'effort' => 'low',
                'kpi_hint' => 'كم منافسًا درست عرضه',
            ]],
        ];
    }

    /**
     * يلتقط ما سمّاه المستخدم من منافسين (نص حر) إلى سجلّ المشروع.
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
     * @return array<int, string>
     */
    private function selectedPlatforms(ToolRun $run): array
    {
        $answer = collect($run->answerMap())->get('ad_platforms');
        $platforms = is_array($answer) && array_key_exists('value', $answer) ? $answer['value'] : $answer;

        return is_array($platforms) ? $platforms : [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     */
    private function writeSections(Report $report, array $sections): void
    {
        foreach ($sections as $section) {
            $report->sections()->create([
                'key' => $section['key'],
                'title' => $section['title'],
                'sort_order' => ($section['sort_order'] ?? 0) + 1,
                'content_json' => $section['content'] ?? [],
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $findings
     */
    private function writeFindings(Report $report, array $findings, ExampleContext $context): void
    {
        foreach (array_values($findings) as $index => $payload) {
            // BR-007: غياب الدليل يجعلها افتراضًا حتى لو ادعى النموذج غير ذلك.
            $isAssumption = (bool) ($payload['is_assumption'] ?? false) || blank($payload['evidence'] ?? null);
            $confidence = (int) ($payload['confidence'] ?? 50);

            // اتساق مع rubric المعايير: الفرضية لا تتجاوز ثقتها 75. يُطبَّق هنا
            // لأنه آخر كاتب — يغطّي ما فرضه الحارس الدلالي وما فرضه BR-007 معًا،
            // فلا يصل للعميل «فرضية بثقة 95».
            if ($isAssumption) {
                $confidence = min($confidence, 75);
            }

            $finding = $report->findings()->create([
                'category' => $payload['category'] ?? 'عام',
                'title' => $payload['title'],
                'description' => $payload['description'],
                'severity' => $payload['severity'] ?? 'medium',
                'evidence' => $payload['evidence'] ?? null,
                'confidence' => $confidence,
                'is_assumption' => $isAssumption,
                'sort_order' => $index,
            ]);

            $this->writeRecommendations($report, $finding, $payload['recommendations'] ?? [], $context);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $recommendations
     */
    private function writeRecommendations(Report $report, Finding $finding, array $recommendations, ExampleContext $context): void
    {
        foreach ($recommendations as $payload) {
            // الخطوات والمثال يمرّان من مسار واحد: مخرج النموذج إن صحّ،
            // وأرضية حتمية إن غاب. لا توصية تصل بلا خطوة ولا بلا مثال.
            $actionable = $this->enricher->enrich($payload, $context);

            Recommendation::create([
                'finding_id' => $finding->id,
                'report_id' => $report->id,
                'title' => $payload['title'],
                'description' => $payload['description'],
                'root_cause' => $payload['root_cause'] ?? $finding->description,
                'commercial_impact' => $payload['commercial_impact'] ?? 'يؤثر في كفاءة النمو أو تكلفة الوصول إلى النتيجة المستهدفة.',
                'action_steps' => $actionable['action_steps'],
                'worked_example' => $actionable['worked_example'],
                'example_source' => $actionable['example_source'],
                'owner_role' => $payload['owner_role'] ?? 'مسؤول التسويق بالتنسيق مع صاحب القرار',
                'resources' => array_values($payload['resources'] ?? ['وقت الفريق', 'بيانات القياس المتاحة']),
                'timeframe' => $payload['timeframe'] ?? 'خلال 30 يومًا',
                'dependencies' => array_values($payload['dependencies'] ?? []),
                'impact' => $payload['impact'] ?? 'medium',
                'effort' => $payload['effort'] ?? 'medium',
                'priority' => $this->priority($payload, $finding),
                'kpi_hint' => $payload['kpi_hint'] ?? null,
                'kpi_definition' => $payload['kpi_definition'] ?? ($payload['kpi_hint'] ?? 'مؤشر النتيجة المرتبط بالتوصية'),
                'kpi_source' => $payload['kpi_source'] ?? 'لوحة القياس المعتمدة للمشروع',
                'baseline' => isset($payload['baseline']) ? (string) $payload['baseline'] : null,
                'target' => isset($payload['target']) ? (string) $payload['target'] : null,
                'missing_baseline_reason' => $payload['missing_baseline_reason'] ?? (! isset($payload['baseline']) ? 'لم يُثبّت خط الأساس بعد؛ يُقاس قبل بدء التنفيذ.' : null),
                'success_condition' => $payload['success_condition'] ?? 'تحسن المؤشر المتفق عليه مقارنة بخط الأساس خلال المدة المحددة.',
                'stop_condition' => $payload['stop_condition'] ?? 'توقف المبادرة وتُراجع إذا لم تتحسن الإشارة المبكرة بعد دورة قياس كاملة.',
                'risks' => array_values($payload['risks'] ?? ['نقص البيانات أو تأخر التنفيذ قد يخفض الثقة في النتيجة.']),
                'confidence' => max(0, min(100, (int) ($payload['confidence'] ?? $finding->confidence))),
            ]);
        }
    }

    /**
     * الأولوية تُحسب هنا لا في النموذج: ترتيب التنفيذ قرار منتج ثابت،
     * ولا يصح أن يتغير ترتيبه بين تشغيلين بنفس المدخلات.
     *
     * @param  array<string, mixed>  $payload
     */
    private function priority(array $payload, Finding $finding): int
    {
        $impact = ['high' => 40, 'medium' => 25, 'low' => 10][$payload['impact'] ?? 'medium'] ?? 25;
        $effort = ['low' => 30, 'medium' => 18, 'high' => 6][$payload['effort'] ?? 'medium'] ?? 18;
        $severity = ['critical' => 30, 'high' => 22, 'medium' => 14, 'low' => 6][$finding->severity] ?? 14;

        // الافتراض غير المدعوم بدليل لا يتصدر قائمة التنفيذ.
        $penalty = $finding->is_assumption ? 12 : 0;

        return max(1, min(100, $impact + $effort + $severity - $penalty));
    }

    /**
     * ضمان حتمي لـ BR-007: كل نتيجة is_assumption=true يظهر أساسها في assumptions.
     *
     * النموذج قد يعلّم نتيجة افتراضًا دون ذكر أساسها، أو يقسرها BR-007 افتراضًا
     * لغياب الدليل. نغلق الفجوة بعد كتابة النتائج: نحقن سطر أساس لكل افتراض غير
     * مذكور، محترمين حدّ المخطط (maxItems:10) وبلا تكرار.
     */
    private function reconcileAssumptions(Report $report): void
    {
        $assumptions = $report->assumptions ?? [];

        $pending = $report->findings()
            ->where('is_assumption', true)
            ->orderBy('sort_order')
            ->get(['title', 'description']);

        foreach ($pending as $finding) {
            if (count($assumptions) >= 10) {
                break; // احترام maxItems:10 — لا نتجاوز الحدّ
            }

            // أساس النتيجة مذكور أصلًا إن ورد عنوانها في أي سطر افتراض قائم.
            $cited = collect($assumptions)->contains(
                fn (string $line) => mb_strpos($line, (string) $finding->title) !== false,
            );

            if ($cited) {
                continue;
            }

            $assumptions[] = "افتراض بلا سند صريح: {$finding->title} — {$finding->description}";
        }

        $assumptions = array_values(array_unique($assumptions));

        if ($assumptions !== ($report->assumptions ?? [])) {
            $report->forceFill(['assumptions' => $assumptions])->save();
        }
    }

    /**
     * @param  array<string, mixed>|null  $synthesis
     * @param  array<string, mixed>|null  $gaps
     * @return array<int, string>
     */
    private function assumptions(?array $synthesis, ?array $gaps): array
    {
        $assumptions = $synthesis['assumptions'] ?? [];

        foreach ($gaps['missing'] ?? [] as $missing) {
            $assumptions[] = "ناقص نعرفه عنك: {$missing['field']} — {$missing['why_it_matters']}";
        }

        foreach ($gaps['conflicts'] ?? [] as $conflict) {
            $assumptions[] = "في كلامك شيئان ما يتفقان: {$conflict['statement']} — {$conflict['explanation']}";
        }

        return array_values(array_unique($assumptions));
    }

    /**
     * @param  array{score: int, band: string}  $baseline
     */
    private function fallbackSummary(array $baseline, bool $usedFloor = false): string
    {
        $head = "درجتك {$baseline['score']} من 100 ({$baseline['band']}). ";

        // حين تعمل الأرضية الحتمية، لا نعتذر: العميل أمامه أولويات حقيقية من إجاباته.
        if ($usedFloor) {
            return $head.'رتّبنا لك أهم النقاط التي تستحق أن تبدأ بها الآن، من الأعلى تأثيرًا في نتيجتك. '
                .'كل نقطة مأخوذة من إجاباتك ودرجتك، لا من تخمين.';
        }

        return $head.'درجتك وإجاباتك محفوظة كاملة، ويمكنك طلب التحليل مجددًا دون إعادة كتابة أي شيء.';
    }

    /**
     * @return array<string, string>
     */
    private function fallbackNextStep(): array
    {
        return [
            'title' => 'اطلب التحليل الموسّع مرة ثانية',
            'description' => 'إجاباتك ودرجتك محفوظة، وإعادة الطلب ما تكلّفك أي كتابة جديدة.',
        ];
    }

    private function inferredConfidence(Report $report): int
    {
        $findings = $report->findings()->count();

        if ($findings === 0) {
            return 20;
        }

        $backed = $report->evidenceBackedFindings()->count();

        return (int) round(30 + ($backed / $findings) * 60);
    }
}
