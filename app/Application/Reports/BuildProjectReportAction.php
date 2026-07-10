<?php

namespace App\Application\Reports;

use App\Contracts\AiGatewayInterface;
use App\Domain\Intelligence\Models\AuditRun;
use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\Tool;
use App\Domain\Tool\Models\ToolRun;
use App\Jobs\WarmProjectReportSynthesisJob;
use App\Support\AI\WorkspaceGenerationContextBuilder;
use App\Support\Dashboard\StageCatalog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * تقرير شامل على مستوى المشروع: يجمع مخرجات كل الأدوات المنجزة، يرتّبها بالمراحل
 * الخمس، ويصوغها كخطة استراتيجية واحدة مترابطة.
 *
 * المحرّك المحلي يبني الهيكل والحقائق (التغطية، الفجوات، تسلسل المراحل) حتمياً؛
 * وLLM (Gemini→NVIDIA) يصوغ التركيب الاستراتيجي والخطة الموحّدة مبنياً على
 * المخرجات الفعلية فقط (بلا اختراع). يتدهور بأمان لتقرير محلي عند غياب LLM.
 */
class BuildProjectReportAction
{
    public function __construct(
        private readonly AiGatewayInterface $gateway,
        private readonly WorkspaceGenerationContextBuilder $contextBuilder,
        private readonly StrategicDiagnosisBuilder $diagnosisBuilder,
        private readonly DomainPlansBuilder $domainPlansBuilder,
        private readonly ProjectDossierBuilder $dossierBuilder,
        private readonly \App\Domain\AI\Knowledge\MarketingKnowledgeBase $knowledge,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(Project $project, bool $fresh = false, bool $allowBlocking = true): array
    {
        $runs = ToolRun::query()
            ->where('project_id', $project->id)
            ->orderByDesc('id')
            ->get()
            ->unique('tool_code');

        $tools = Tool::query()->get()->keyBy('code');

        // بناء الأقسام حسب المرحلة (1..5) من المخرجات الفعلية.
        $stages = [];
        $completedCodes = [];
        $qualitySum = 0;
        $qualityCount = 0;
        $contentQualitySum = 0;
        $contentQualityCount = 0;

        foreach ($runs as $run) {
            $tool = $tools->get($run->tool_code);
            if (! $tool) {
                continue;
            }
            $stage = (int) $tool->stage;
            $headline = trim((string) data_get($run->summary_json, 'headline', ''));
            $bullets = array_values(array_filter(array_map(
                fn ($b): string => trim((string) $b),
                (array) data_get($run->summary_json, 'bullets', []),
            )));
            $score = (int) ($run->completeness_score ?? 0);

            $stages[$stage]['items'][] = [
                'tool' => $run->tool_code,
                'tool_name' => (string) $tool->name,
                'headline' => $headline !== '' ? $headline : (string) $tool->name,
                'points' => array_slice($bullets, 0, 4),
                'score' => $score,
            ];
            $completedCodes[] = $run->tool_code;
            $qualitySum += $score;
            $qualityCount++;

            // جودة المحتوى الحقيقية (§8): من تقييم QualityJudge المخزَّن، لا الاكتمال.
            $contentQuality = data_get($run->output_json, 'content_quality.score');
            if (is_numeric($contentQuality)) {
                $contentQualitySum += (int) $contentQuality;
                $contentQualityCount++;
            }
        }

        // التغطية والفجوات لكل مرحلة (من core_tools في StageCatalog).
        $completedCodes = array_unique($completedCodes);
        $gaps = [];
        $totalCore = 0;
        $doneCore = 0;
        for ($s = 1; $s <= 5; $s++) {
            $stageDef = StageCatalog::all()[$s] ?? null;
            $core = (array) ($stageDef['core_tools'] ?? []);
            $totalCore += count($core);
            $missing = [];
            foreach ($core as $code) {
                if (in_array($code, $completedCodes, true)) {
                    $doneCore++;
                } else {
                    $missing[] = (string) ($tools->get($code)?->name ?? $code);
                }
            }
            $stages[$s]['label'] = StageCatalog::label($s);
            $stages[$s]['items'] = $stages[$s]['items'] ?? [];
            $stages[$s]['missing'] = $missing;
            if ($missing !== []) {
                $gaps[] = 'المرحلة «'.StageCatalog::label($s).'» تنقصها: '.implode('، ', array_slice($missing, 0, 4));
            }
        }
        ksort($stages);

        $completion = $totalCore > 0 ? (int) round($doneCore / $totalCore * 100) : 0;
        $avgQuality = $qualityCount > 0 ? (int) round($qualitySum / $qualityCount) : 0;
        $avgContentQuality = $contentQualityCount > 0
            ? (int) round($contentQualitySum / $contentQualityCount)
            : null;

        // دمج التدقيق الذكي (إن وُجد): تشخيص فني مفصّل + درجات + خطة 7/30/90 جاهزة.
        $audit = $this->auditSnapshot($project);

        // التشخيص الاستراتيجي المتقاطع: ثلاثيات (مشكلة ← سبب فعلي ← حل واقعي)
        // مستخرَجة من إجابات كل الأدوات — قلب التقرير.
        $diagnosis = $this->diagnosisBuilder->build($runs);

        // خطط المجالات الكاملة (محتوى/ترويج/عرض/رحلة/أداء) مشتقّة من المدخلات.
        $domainPlans = $this->domainPlansBuilder->build($runs);

        $base = [
            'project' => $project->name,
            'client' => $project->client?->name,
            'completion' => $completion,
            'avg_quality' => $avgQuality,
            'content_quality' => $avgContentQuality,
            'tools_completed' => count($completedCodes),
            'stages' => array_values($stages),
            'gaps' => $gaps,
            'audit' => $audit,
            'diagnosis' => $diagnosis,
            'domain_plans' => $domainPlans,
        ];

        if (count($completedCodes) === 0) {
            $base['executive_summary'] = 'لم تُنجَز أدوات بعد لهذا المشروع. ابدأ بمرحلة «اعرف وضعك» لتظهر هنا خطة استراتيجية مترابطة.';
            $base['priorities'] = [];
            $base['plan'] = ['quick_wins_7' => [], 'improvements_30' => [], 'strategic_90' => []];
            $base['synthesis_source'] = 'local';

            return $base;
        }

        // التركيب الاستراتيجي عبر LLM (نداء واحد، مُكاش، مبني على المخرجات فقط).
        // قاعدة معمارية: لا يُستدعى LLM بشكل متزامن داخل طلب ويب — فقد يحجب حتى
        // 90 ثانية عند تعثّر المزوّد فيتجاوز مهلة الخادم وينتج خطأ 500. المسار غير
        // المحجوب (allowBlocking=false) يعرض تقريراً محلياً حتمياً فوراً، ويُفوّض
        // التركيب الذكي لطابور الخلفية فيظهر عند التحديث التالي.
        $fingerprint = hash('sha256', json_encode($base['stages'], JSON_UNESCAPED_UNICODE).'|'.$completion);
        $cacheKey = 'project_report:v1:'.$project->id.':'.$fingerprint;

        $cached = $fresh ? null : Cache::get($cacheKey);
        if (is_array($cached)) {
            return array_merge($base, $cached);
        }

        // مسار محجوب (CLI / Queue / تصدير غير حسّاس للزمن): ولّد التركيب الآن وخزّنه.
        if ($allowBlocking) {
            $synthesis = $this->synthesize($project, $base);
            if ($synthesis !== null) {
                Cache::put($cacheKey, $synthesis, now()->addHours(12));

                return array_merge($base, $synthesis);
            }

            return array_merge($base, $this->localSynthesis($completedCodes, $completion, $gaps, $diagnosis));
        }

        // مسار الويب: لا تحجب. دفّئ الكاش في الخلفية (قفل يمنع تكرار الإرسال) واعرض المحلي.
        if (Cache::add('project_report:warm:'.$cacheKey, 1, now()->addMinutes(3))) {
            WarmProjectReportSynthesisJob::dispatch($project->id);
        }

        return array_merge($base, $this->localSynthesis($completedCodes, $completion, $gaps, $diagnosis));
    }

    /**
     * تركيب محلي حتمي — يُستخدم عند غياب أو تأجيل تركيب LLM (بلا أي نداء خارجي).
     *
     * @param  array<int, string>  $completedCodes
     * @param  array<int, string>  $gaps
     * @param  array<string, mixed>  $diagnosis
     * @return array<string, mixed>
     */
    private function localSynthesis(array $completedCodes, int $completion, array $gaps, array $diagnosis = []): array
    {
        // الأولويات من المشاكل المُشخّصة (محتوى متميّز)، لا تكراراً لقائمة الفجوات.
        $priorities = array_values(array_filter(array_map(
            fn ($p): string => trim((string) ($p['problem'] ?? '')),
            array_slice((array) ($diagnosis['problems'] ?? []), 0, 3),
        )));
        if ($priorities === []) {
            $priorities = array_slice($gaps, 0, 3);
        }

        return [
            'executive_summary' => 'تقرير مبني على '.count($completedCodes).' أداة منجَزة بنسبة تغطية '.$completion.'%. راجع الأقسام أدناه والفجوات لإكمال الصورة.',
            'priorities' => $priorities,
            'plan' => ['quick_wins_7' => [], 'improvements_30' => [], 'strategic_90' => []],
            'synthesis_source' => 'local',
        ];
    }

    /**
     * لقطة من آخر تدقيق ذكي مكتمل للمشروع (تشخيص فني + درجات + خطة 7/30/90 جاهزة).
     *
     * @return array<string, mixed>|null
     */
    private function auditSnapshot(Project $project): ?array
    {
        $audit = AuditRun::query()
            ->where('project_id', $project->id)
            ->where('status', 'completed')
            ->latest('id')
            ->first();

        if ($audit === null) {
            return null;
        }

        $r = (array) $audit->report_json;
        $strList = static fn ($v): array => array_slice(array_values(array_filter(
            array_map(fn ($x): string => trim((string) $x), (array) $v),
            fn (string $x): bool => $x !== '',
        )), 0, 6);

        $problems = data_get($r, 'top_5_problems', data_get($r, 'honest_diagnosis.top_5_problems', []));
        $pa = data_get($r, 'priority_actions', data_get($r, 'honest_diagnosis.priority_actions', []));
        $exec = data_get($r, 'executive_scores.executive');

        $topProblems = $strList($problems);

        // إزالة تكرار الإجراءات عبر الآفاق الزمنية: لا يُعاد نفس الإجراء في أفقين.
        $seen = [];
        $dedupe = static function (array $items) use (&$seen): array {
            $out = [];
            foreach ($items as $it) {
                $k = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $it)));
                if ($k === '' || isset($seen[$k])) {
                    continue;
                }
                $seen[$k] = true;
                $out[] = $it;
            }

            return $out;
        };

        // كشف تعذّر الوصول للموقع: الدرجة حينها مبنية على البيانات لا الموقع الحيّ.
        $unreachable = false;
        foreach ($topProblems as $p) {
            if ($this->mentionsUnreachable((string) $p)) {
                $unreachable = true;
                break;
            }
        }

        return [
            'executive_score' => $exec !== null ? (int) $exec : null,
            'site_unreachable' => $unreachable,
            'top_problems' => $topProblems,
            'quick_wins_7' => $dedupe($strList(data_get($pa, 'quick_wins_7_days', []))),
            'improvements_30' => $dedupe($strList(data_get($pa, 'improvements_30_days', []))),
            'strategic_90' => $dedupe($strList(data_get($pa, 'strategic_90_days', []))),
            'completed_at' => optional($audit->completed_at)->toDateString(),
        ];
    }

    /** هل يشير نص المشكلة إلى تعذّر الوصول للموقع الحيّ؟ */
    private function mentionsUnreachable(string $text): bool
    {
        foreach (['غير قابل للوصول', 'لا يمكن الوصول', 'تعذّر الوصول', 'تعذر الوصول', 'لم يُفتح', 'لم يفتح', 'inaccessible', 'not accessible', 'unreachable'] as $needle) {
            if (mb_stripos($text, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>|null
     */
    private function synthesize(Project $project, array $base): ?array
    {
        $context = $this->contextBuilder->promptBlockForIds($project->workspace_id, $project->id);

        // النظام الهجين: نُغذّي الـLLM «دليل المشروع» كاملاً (كل الإجابات الخام
        // المجمّعة حتمياً) بدل ملخّصات مضغوطة — فيحلّل المادة الكاملة ويربطها.
        $dossierMarkdown = $this->dossierBuilder->build($project)['markdown'];

        // تأريض دور «التطوير» الخارجي بمعرفة محلية: أطر + معايير قطاعية + أنماط
        // ذات صلة بمشاكل المشروع — فيستند LLM إلى معرفتنا لا إلى عمومياته.
        $knowledgeQuery = trim(implode(' ', array_merge(
            array_map(
                fn (array $p): string => (string) ($p['problem'] ?? ''),
                array_slice((array) ($base['diagnosis']['problems'] ?? []), 0, 3),
            ),
            (array) ($base['gaps'] ?? []),
        )));
        $knowledgeBlock = $this->knowledge->promptBlock(
            $knowledgeQuery !== '' ? $knowledgeQuery : (string) $base['project'],
            $project->sector,
            4,
        );

        $prompt = implode("\n", [
            'أنت مستشار استراتيجي. بين يديك «دليل المشروع» كاملاً — كل إجابات صاحب المشروع الخام عبر كل الأدوات، مجمّعةً ومرتّبةً حسب المراحل.',
            'اقرأ الدليل بالكامل واكتب تقريراً استراتيجياً **مترابطاً** يربط القطع ببعضها (لا تكرار حرفي للإجابات)، مبنياً على ما ورد فيه فقط دون اختراع أرقام أو حقائق.',
            '',
            $context !== '' ? $context : '',
            '',
            '=== معرفة تسويقية مرجعية (استند إليها ولا تخترع أرقاماً خارجها) ===',
            $knowledgeBlock,
            '',
            '=== دليل المشروع (الإجابات الخام الكاملة) ===',
            $dossierMarkdown,
            '',
            ! empty($base['audit']['top_problems'])
                ? '=== تشخيص فني من التدقيق الذكي (ادمجه) ===' . "\n" . '- ' . implode("\n- ", $base['audit']['top_problems'])
                : '',
            '',
            'نسبة اكتمال المراحل: '.$base['completion'].'% — الفجوات: '.(implode(' | ', $base['gaps']) ?: 'لا فجوات'),
            '',
            'أعد JSON فقط بهذا الشكل:',
            '{"executive_summary":"فقرتان: أين يقف المشروع استراتيجياً وكيف تترابط أجزاؤه وأهم خطر/فرصة",',
            '"priorities":["أولوية 1","أولوية 2","أولوية 3"],',
            '"plan":{"quick_wins_7":["إجراء خلال 7 أيام"],"improvements_30":["إجراء خلال 30 يوماً"],"strategic_90":["إجراء استراتيجي خلال 90 يوماً"]}}',
        ]);

        $system = 'أنت مستشار استراتيجي خبير. تربط مخرجات الأدوات في خطة واحدة مترابطة مبنية على المعطيات فقط. أعد JSON صالحاً فقط بلا أي نص حوله.';

        $text = $this->gateway->generateText($prompt, $system);
        if (! $text) {
            return null;
        }

        $clean = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text));
        $parsed = json_decode((string) $clean, true);
        if (! is_array($parsed)) {
            $start = strpos((string) $clean, '{');
            $end = strrpos((string) $clean, '}');
            if ($start !== false && $end !== false && $end > $start) {
                $parsed = json_decode(substr((string) $clean, $start, $end - $start + 1), true);
            }
        }
        if (! is_array($parsed) || empty($parsed['executive_summary'])) {
            return null;
        }

        $list = static fn ($v): array => array_values(array_filter(array_map(
            fn ($x): string => trim((string) $x),
            (array) $v,
        ), fn (string $x): bool => $x !== ''));

        // إزالة أي أولوية كرّرت نص فجوة حرفياً (يظهر خلاف ذلك مرّتين: أولويات + فجوات).
        $gapSet = array_map(fn ($g): string => trim((string) $g), (array) ($base['gaps'] ?? []));
        $priorities = array_values(array_filter(
            array_slice($list($parsed['priorities'] ?? []), 0, 5),
            fn (string $p): bool => ! in_array(trim($p), $gapSet, true),
        ));

        return [
            'executive_summary' => Str::limit(trim((string) $parsed['executive_summary']), 1200, '…'),
            'priorities' => $priorities,
            'plan' => [
                'quick_wins_7' => array_slice($list(data_get($parsed, 'plan.quick_wins_7', [])), 0, 6),
                'improvements_30' => array_slice($list(data_get($parsed, 'plan.improvements_30', [])), 0, 6),
                'strategic_90' => array_slice($list(data_get($parsed, 'plan.strategic_90', [])), 0, 6),
            ],
            'synthesis_source' => 'llm',
        ];
    }
}
