<?php

namespace App\Services\Tools;

use App\Models\Finding;
use App\Models\Recommendation;
use App\Models\Report;
use App\Models\ToolRun;
use App\Services\Competitors\CompetitorRegistry;
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

            $this->writeScoreSection($report, $baseline);
            $this->writeSections($report, $sections);
            $this->writeCompetitorSection($report, $run, $competitorView);
            $this->writeFindings($report, $findings);

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
    private function writeScoreSection(Report $report, array $baseline): void
    {
        $report->sections()->create([
            'key' => 'score',
            'title' => 'الدرجة وتفصيلها',
            'sort_order' => 0,
            'content_json' => [
                'score' => $baseline['score'],
                'band' => $baseline['band'],
                'breakdown' => $baseline['breakdown'],
                'method' => 'قواعد حتمية — لا يشارك الذكاء الاصطناعي في احتساب هذه الدرجة.',
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
                'intro' => 'أقوى تحليل للمنافسة يبدأ من منافسيك المحليين — أنت أعرف بهم منّا. '
                    .'هنا نجمع من سمّيتهم، وأين ترى إعلانات الجميع، وما تبحث عنه بالضبط.',
                'confirmed' => $competitorView['confirmed'],
                'candidates' => $competitorView['candidates'],
                // دعوة صريحة حين لا يكون قد سمّى محليًا: هم الأهم.
                'prompt_local' => ! $competitorView['has_local'],
                'watchlist' => $watchlist,
                'look_for' => [
                    'العرض الذي يكررونه (خصم، ضمان، توصيل مجاني) — هو ما يجذب عملاءهم، وتحتاج ما يوازيه أو يتفوّق عليه.',
                    'الإعلان الذي بقي نشطًا مدة طويلة — استمراره دليل أنه يبيع، فادرس زاويته ورسالته.',
                    'شكل الإعلان ونبرته (فيديو، صورة، شهادة عميل) — لتعرف ما اعتاد جمهورك التفاعل معه.',
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

            $title = 'راقب إعلانات منافسيك المحليين';
            $recTitle = "افحص إعلانات: {$names}";
            $recDescription = 'افتح مكتبات الإعلانات، وابحث عن كل واحد منهم، وسجّل عرضهم المتكرر '
                .'وأطول إعلان بقي نشطًا. أنجز هذا قبل ضبط رسالتك.';
        } else {
            $title = 'حدّد منافسيك المحليين أولًا';
            $recTitle = 'اكتب اسمين أو ثلاثة لمنافسيك المحليين';
            $recDescription = 'من يأخذ عملاءك في مدينتك هو من يوجّه خطتك، لا العلامات الكبيرة البعيدة. '
                .'اكتب من يذكره عميلك حين يقارن، ومن أخذ عميلًا توقعته — ثم راقب إعلاناتهم.';
        }

        return [
            'title' => $title,
            'description' => 'المنافسة المحلية مصدر أغلب ضغط السوق عليك. مراقبتها المنظمة أرخص بحث سوق وأدقّه.',
            'category' => 'المنافسون',
            'severity' => 'medium',
            'is_assumption' => false,
            'evidence' => $competitorView['has_local']
                ? 'مبني على المنافسين الذين سمّيتهم.'
                : 'لم تُسجَّل أسماء منافسين محليين بعد.',
            'confidence' => 85,
            'recommendations' => [[
                'title' => $recTitle,
                'description' => $recDescription,
                'impact' => 'high',
                'effort' => 'low',
                'kpi_hint' => 'عدد المنافسين الذين درست عروضهم',
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
    private function writeFindings(Report $report, array $findings): void
    {
        foreach (array_values($findings) as $index => $payload) {
            $finding = $report->findings()->create([
                'category' => $payload['category'] ?? 'عام',
                'title' => $payload['title'],
                'description' => $payload['description'],
                'severity' => $payload['severity'] ?? 'medium',
                'evidence' => $payload['evidence'] ?? null,
                'confidence' => (int) ($payload['confidence'] ?? 50),
                // BR-007: غياب الدليل يجعلها افتراضًا حتى لو ادعى النموذج غير ذلك.
                'is_assumption' => (bool) ($payload['is_assumption'] ?? false) || blank($payload['evidence'] ?? null),
                'sort_order' => $index,
            ]);

            $this->writeRecommendations($report, $finding, $payload['recommendations'] ?? []);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $recommendations
     */
    private function writeRecommendations(Report $report, Finding $finding, array $recommendations): void
    {
        foreach ($recommendations as $payload) {
            Recommendation::create([
                'finding_id' => $finding->id,
                'report_id' => $report->id,
                'title' => $payload['title'],
                'description' => $payload['description'],
                'impact' => $payload['impact'] ?? 'medium',
                'effort' => $payload['effort'] ?? 'medium',
                'priority' => $this->priority($payload, $finding),
                'kpi_hint' => $payload['kpi_hint'] ?? null,
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
     * @param  array<string, mixed>|null  $synthesis
     * @param  array<string, mixed>|null  $gaps
     * @return array<int, string>
     */
    private function assumptions(?array $synthesis, ?array $gaps): array
    {
        $assumptions = $synthesis['assumptions'] ?? [];

        foreach ($gaps['missing'] ?? [] as $missing) {
            $assumptions[] = "بيانات ناقصة: {$missing['field']} — {$missing['why_it_matters']}";
        }

        foreach ($gaps['conflicts'] ?? [] as $conflict) {
            $assumptions[] = "تعارض: {$conflict['statement']} — {$conflict['explanation']}";
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
            return $head.'رتّبنا لك أهم الجوانب التي تستحق التحسين الآن، مبدوءة بالأعلى أثرًا على نتيجتك. '
                .'كل أولوية مشتقة من إجاباتك ودرجتك، لا من تخمين.';
        }

        return $head.'الدرجة وإجاباتك محفوظة بالكامل، ويمكنك إعادة طلب التحليل دون إعادة إدخال أي بيانات.';
    }

    /**
     * @return array<string, string>
     */
    private function fallbackNextStep(): array
    {
        return [
            'title' => 'أعد طلب التحليل الموسع',
            'description' => 'إجاباتك ودرجتك محفوظة. إعادة الطلب لا تكلفك إدخالًا جديدًا.',
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
