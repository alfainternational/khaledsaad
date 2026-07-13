<?php

namespace App\Application\Reports;

use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\Tool;
use App\Domain\Tool\Models\ToolRun;
use App\Support\Dashboard\StageCatalog;
use App\Support\Tooling\ToolBlueprintCatalog;
use Illuminate\Support\Collection;

/**
 * مُجمّع «دليل المشروع» — يجمع كل بيانات المشروع وإجاباته الخام من كل الأدوات في
 * وثيقة واحدة موحّدة ومرتّبة (حسب المراحل الخمس ثم الأدوات ثم السؤال ← الجواب).
 *
 * حتمي بالكامل — لا LLM ولا اختلاق. يخدم غرضين:
 *   (1) وثيقة قابلة للطباعة/التسليم كما هي (الإجابات الخام كدليل).
 *   (2) الركيزة التي يقرأ منها تركيب LLM في التقرير النهائي (النظام الهجين):
 *       الـLLM يحلّل الدليل الكامل فوق الأساس المحلي، لا ملخّصات مضغوطة.
 *
 * @see BuildProjectReportAction محرّك التقرير الذي يستهلك markdown هذا الدليل.
 */
class ProjectDossierBuilder
{
    public function __construct(
        private readonly ToolBlueprintCatalog $blueprints,
    ) {}

    /**
     * @return array{
     *   meta: array<string, mixed>,
     *   stages: array<int, array<string, mixed>>,
     *   markdown: string,
     *   has_answers: bool
     * }
     */
    public function build(Project $project): array
    {
        // آخر تشغيل لكل أداة (الأحدث يفوز)، مفهرساً بكود الأداة.
        $runs = ToolRun::query()
            ->where('project_id', $project->id)
            ->orderByDesc('id')
            ->get()
            ->unique('tool_code')
            ->keyBy('tool_code');

        $tools = Tool::query()->get()->keyBy('code');

        $stages = [];
        $completedCount = 0;
        $totalCore = 0;
        $doneCore = 0;

        for ($s = 1; $s <= 5; $s++) {
            $stageDef = StageCatalog::all()[$s] ?? null;
            $core = (array) ($stageDef['core_tools'] ?? []);
            $totalCore += count($core);

            // ترتيب الأدوات: الأساسية بترتيب المرحلة أولاً، ثم أي أدوات منجَزة إضافية.
            $stageToolCodes = $this->stageToolOrder($s, $core, $runs, $tools);

            $stageTools = [];
            $missing = [];
            foreach ($stageToolCodes as $code) {
                $run = $runs->get($code);
                if ($run === null) {
                    if (in_array($code, $core, true)) {
                        $missing[] = (string) ($tools->get($code)?->name ?? $code);
                    }

                    continue;
                }

                if (in_array($code, $core, true)) {
                    $doneCore++;
                }
                $completedCount++;
                $stageTools[] = $this->toolEntry($run, $tools->get($code));
            }

            $coreDone = count(array_intersect($core, array_keys($runs->all())));
            $stages[] = [
                'num' => $s,
                'label' => StageCatalog::label($s),
                'description' => StageCatalog::description($s),
                'completion' => count($core) > 0 ? (int) round($coreDone / count($core) * 100) : 0,
                'tools' => $stageTools,
                'missing' => $missing,
            ];
        }

        $meta = [
            'name' => (string) $project->name,
            'client' => $project->client?->name,
            'sector' => $project->sector,
            'market_country' => $project->market_country,
            'primary_domain' => $project->primary_domain,
            'stage_label' => StageCatalog::label((int) $project->stage),
            'tools_completed' => $completedCount,
            'completion' => $totalCore > 0 ? (int) round($doneCore / $totalCore * 100) : 0,
        ];

        return [
            'meta' => $meta,
            'stages' => $stages,
            'markdown' => $this->markdown($meta, $stages),
            'has_answers' => $completedCount > 0,
        ];
    }

    /**
     * صفّ أداة واحدة: خلاصتها + إجاباتها الخام (سؤال ← جواب) بعناوين بشرية.
     *
     * @return array<string, mixed>
     */
    private function toolEntry(ToolRun $run, ?Tool $tool): array
    {
        $labels = $this->blueprints->fieldLabelMap($run->tool_code, $run->mode);
        $inputs = (array) ($run->inputs_json ?? []);

        $answers = [];
        foreach ($inputs as $key => $value) {
            $text = $this->formatValue($value);
            if ($text === '') {
                continue;
            }
            $answers[] = [
                'label' => (string) ($labels[$key]['label'] ?? $this->humanizeKey((string) $key)),
                'value' => $text,
            ];
        }

        $bullets = array_values(array_filter(array_map(
            fn ($b): string => trim((string) $b),
            (array) data_get($run->summary_json, 'bullets', []),
        )));

        return [
            'code' => $run->tool_code,
            'name' => (string) ($tool?->name ?? $run->tool_code),
            'completeness' => (int) ($run->completeness_score ?? 0),
            'answered_at' => optional($run->created_at)->toDateString(),
            'headline' => trim((string) data_get($run->summary_json, 'headline', '')),
            'bullets' => $bullets,
            'answers' => $answers,
        ];
    }

    /**
     * ترتيب أكواد أدوات المرحلة: الأساسية بترتيبها، ثم المنجَزة غير الأساسية.
     *
     * @param  array<int, string>  $core
     * @param  Collection<string, ToolRun>  $runs
     * @param  Collection<string, Tool>  $tools
     * @return array<int, string>
     */
    private function stageToolOrder(int $stage, array $core, Collection $runs, Collection $tools): array
    {
        $extra = $tools
            ->filter(fn (Tool $t): bool => (int) $t->stage === $stage && ! in_array($t->code, $core, true))
            ->filter(fn (Tool $t): bool => $runs->has($t->code))
            ->keys()
            ->all();

        return array_values(array_unique([...$core, ...$extra]));
    }

    private function formatValue(mixed $value): string
    {
        if (is_array($value)) {
            $parts = array_filter(
                array_map(fn ($x): string => is_scalar($x) ? trim((string) $x) : '', $value),
                fn (string $x): bool => $x !== '',
            );

            return implode('، ', $parts);
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function humanizeKey(string $key): string
    {
        return trim(str_replace(['_', '-'], ' ', $key));
    }

    /**
     * وثيقة Markdown موحّدة — تُطبع كما هي وتُغذّي تحليل LLM.
     *
     * @param  array<string, mixed>  $meta
     * @param  array<int, array<string, mixed>>  $stages
     */
    private function markdown(array $meta, array $stages): string
    {
        $lines = [];
        $lines[] = '# دليل المشروع: '.$meta['name'];

        $metaBits = array_filter([
            $meta['client'] ? 'العميل: '.$meta['client'] : null,
            $meta['sector'] ? 'القطاع: '.$meta['sector'] : null,
            $meta['market_country'] ? 'السوق: '.$meta['market_country'] : null,
            $meta['primary_domain'] ? 'النطاق: '.$meta['primary_domain'] : null,
            'المرحلة الحالية: '.$meta['stage_label'],
            'اكتمال المراحل: '.$meta['completion'].'%',
            'أدوات منجَزة: '.$meta['tools_completed'],
        ]);
        $lines[] = implode(' · ', $metaBits);

        foreach ($stages as $stage) {
            $lines[] = '';
            $lines[] = '## المرحلة '.$stage['num'].': '.$stage['label'].' ('.$stage['completion'].'%)';
            $lines[] = $stage['description'];

            if ($stage['tools'] === []) {
                $lines[] = '_لم تُنجَز أي أداة في هذه المرحلة بعد._';
            }

            foreach ($stage['tools'] as $tool) {
                $lines[] = '';
                $head = '### '.$tool['name'];
                $sub = array_filter([
                    $tool['answered_at'] ? 'بتاريخ '.$tool['answered_at'] : null,
                    'اكتمال '.$tool['completeness'].'%',
                ]);
                $lines[] = $head.' ('.implode(' · ', $sub).')';

                if ($tool['headline'] !== '') {
                    $lines[] = 'الخلاصة: '.$tool['headline'];
                }
                foreach ($tool['bullets'] as $b) {
                    $lines[] = '- '.$b;
                }

                if ($tool['answers'] !== []) {
                    $lines[] = 'الإجابات:';
                    foreach ($tool['answers'] as $a) {
                        $lines[] = '- '.$a['label'].': '.$a['value'];
                    }
                }
            }

            if ($stage['missing'] !== []) {
                $lines[] = '';
                $lines[] = 'أدوات أساسية ناقصة في هذه المرحلة: '.implode('، ', $stage['missing']).'.';
            }
        }

        return implode("\n", $lines);
    }
}
