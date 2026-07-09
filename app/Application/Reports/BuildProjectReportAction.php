<?php

namespace App\Application\Reports;

use App\Contracts\AiGatewayInterface;
use App\Domain\Intelligence\Models\AuditRun;
use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\Tool;
use App\Domain\Tool\Models\ToolRun;
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
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(Project $project, bool $fresh = false): array
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
        ];

        if (count($completedCodes) === 0) {
            $base['executive_summary'] = 'لم تُنجَز أدوات بعد لهذا المشروع. ابدأ بمرحلة «اعرف وضعك» لتظهر هنا خطة استراتيجية مترابطة.';
            $base['priorities'] = [];
            $base['plan'] = ['quick_wins_7' => [], 'improvements_30' => [], 'strategic_90' => []];
            $base['synthesis_source'] = 'local';

            return $base;
        }

        // التركيب الاستراتيجي عبر LLM (نداء واحد، مُكاش، مبني على المخرجات فقط).
        $fingerprint = hash('sha256', json_encode($base['stages'], JSON_UNESCAPED_UNICODE).'|'.$completion);
        $cacheKey = 'project_report:v1:'.$project->id.':'.$fingerprint;

        $synthesis = $fresh
            ? $this->synthesize($project, $base)
            : Cache::remember($cacheKey, now()->addHours(12), fn () => $this->synthesize($project, $base));

        return array_merge($base, $synthesis ?? [
            'executive_summary' => 'تقرير مبني على '.count($completedCodes).' أداة منجَزة بنسبة تغطية '.$completion.'%. راجع الأقسام أدناه والفجوات لإكمال الصورة.',
            'priorities' => array_slice($gaps, 0, 3),
            'plan' => ['quick_wins_7' => [], 'improvements_30' => [], 'strategic_90' => []],
            'synthesis_source' => 'local',
        ]);
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

        return [
            'executive_score' => $exec !== null ? (int) $exec : null,
            'top_problems' => $strList($problems),
            'quick_wins_7' => $strList(data_get($pa, 'quick_wins_7_days', [])),
            'improvements_30' => $strList(data_get($pa, 'improvements_30_days', [])),
            'strategic_90' => $strList(data_get($pa, 'strategic_90_days', [])),
            'completed_at' => optional($audit->completed_at)->toDateString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>|null
     */
    private function synthesize(Project $project, array $base): ?array
    {
        $context = $this->contextBuilder->promptBlockForIds($project->workspace_id, $project->id);

        $stagesBlock = collect($base['stages'])->map(function (array $st): string {
            $items = collect($st['items'] ?? [])->map(
                fn (array $i): string => '  • '.$i['tool_name'].': '.$i['headline'].($i['points'] ? ' — '.implode('؛ ', $i['points']) : '')
            )->implode("\n");

            return 'مرحلة «'.$st['label'].'»:'."\n".($items !== '' ? $items : '  (لا أدوات منجَزة)');
        })->implode("\n\n");

        $prompt = implode("\n", [
            'أنت مستشار استراتيجي. لديك مخرجات أدوات منجَزة لمشروع «'.$base['project'].'».',
            'اكتب تقريراً استراتيجياً **مترابطاً** يربط القطع ببعضها (لا تكرار للمخرجات)، مبنياً على ما هو موجود فقط دون اختراع أرقام أو حقائق.',
            '',
            $context !== '' ? $context : '',
            '',
            '=== مخرجات الأدوات حسب المرحلة ===',
            $stagesBlock,
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

        return [
            'executive_summary' => Str::limit(trim((string) $parsed['executive_summary']), 1200, '…'),
            'priorities' => array_slice($list($parsed['priorities'] ?? []), 0, 5),
            'plan' => [
                'quick_wins_7' => array_slice($list(data_get($parsed, 'plan.quick_wins_7', [])), 0, 6),
                'improvements_30' => array_slice($list(data_get($parsed, 'plan.improvements_30', [])), 0, 6),
                'strategic_90' => array_slice($list(data_get($parsed, 'plan.strategic_90', [])), 0, 6),
            ],
            'synthesis_source' => 'llm',
        ];
    }
}
