<?php

namespace App\Support\Tooling;

use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\Tool;
use App\Domain\Tool\Models\ToolRun;
use App\Domain\WorkspaceData\Models\WorkspaceData;
use Illuminate\Support\Str;

/**
 * محرك الملء المسبق مع مصدر واضح (Provenance) — المرحلة الأولى من تطوير الإدخال.
 *
 * الفلسفة (راجع نقاش الإدخال/الذكاء): افصل الحقيقة عن الاستنتاج عن الصياغة.
 * هنا نملأ الحقول مسبقاً من مصادر *موثوقة* عالية الثقة قبل الوقوع على heuristics
 * الصياغة العامة في ToolFormExperienceBuilder، ونرفق مع كل اقتراح مصدره حتى
 * يعرف المستخدم (والواجهة) من أين جاء: إجابته السابقة أم أداة أخرى.
 *
 * أولوية المصادر (الأعلى ثقةً أولاً):
 *   1. previous_run     — إجابة المستخدم نفسه في آخر تشغيل لنفس الأداة والحقل.
 *   2. derived_from_tool — قيمة معيارية (canonical) أنتجتها أداة أخرى تُغذّي هذا الحقل.
 *
 * نقي وحتمي في مساره الأساسي (previous_run) وقابل للاختبار مباشرةً؛ مسار
 * derived_from_tool يقرأ workspace_data المعياري الذي يكتبه RunToolAction.
 */
class ToolInputPrefillService
{
    /**
     * خريطة الاستهلاك: مفتاح معياري (canonical) → حقول الأدوات الأخرى التي يُغذّيها.
     * عكس CanonicalOutputMapper::MAP (الذي يصف الإنتاج). المفاتيح محدودة enum
     * معروف (الدستور §28) — أي إضافة تمرّ بمراجعة PR.
     *
     * @var array<string, array<int, string>>
     */
    private const CONSUMES = [
        'ideal_customer' => ['offer_audience', 'promise_audience', 'campaign_audience', 'content_audience'],
        'offer' => ['pricing_offer', 'core_offer', 'campaign_offer'],
        'positioning' => ['main_difference'],
        'market' => ['market_segment'],
    ];

    /** @var array<string, string> */
    private array $toolNameCache = [];

    /**
     * يعيد اقتراحاً واحداً عالي الثقة لكل حقل (إن وُجد) مع مصدره.
     *
     * @param  array<int, string>  $fieldKeys  مفاتيح حقول الأداة (لكل الأوضاع)
     * @return array<string, array{value: string, source: string, label: string}>
     */
    public function suggest(Tool $tool, array $fieldKeys, ?ToolRun $latestRun, ?Project $project): array
    {
        $suggestions = [];

        // (1) إجابة المستخدم السابقة لنفس الأداة — أعلى ثقة، لا نعيد سؤاله عمّا كتبه.
        if ($latestRun instanceof ToolRun && $latestRun->tool_code === $tool->code) {
            $inputs = is_array($latestRun->inputs_json) ? $latestRun->inputs_json : [];
            foreach ($fieldKeys as $key) {
                $value = $inputs[$key] ?? null;
                if (is_string($value) && trim($value) !== '') {
                    $suggestions[$key] = [
                        'value' => trim($value),
                        'source' => 'previous_run',
                        'label' => 'من إجابتك السابقة',
                    ];
                }
            }
        }

        // (2) قيم معيارية من أدوات أخرى تُغذّي حقول هذه الأداة.
        $canonical = $this->canonicalSuggestions($tool, $fieldKeys, $project);
        foreach ($canonical as $key => $suggestion) {
            // لا نطغى على إجابة المستخدم السابقة الأعلى ثقةً.
            $suggestions[$key] ??= $suggestion;
        }

        return $suggestions;
    }

    /**
     * @param  array<int, string>  $fieldKeys
     * @return array<string, array{value: string, source: string, label: string}>
     */
    private function canonicalSuggestions(Tool $tool, array $fieldKeys, ?Project $project): array
    {
        if (! $project instanceof Project) {
            return [];
        }

        $fieldSet = array_flip($fieldKeys);
        $suggestions = [];

        $rows = WorkspaceData::query()
            ->where('workspace_id', $project->workspace_id)
            ->where('project_id', $project->id)
            ->whereIn('key', array_keys(self::CONSUMES))
            ->get();

        foreach ($rows as $row) {
            $canonicalKey = (string) $row->key;
            $payload = is_array($row->value_json) ? $row->value_json : [];
            $value = $payload['value'] ?? null;
            $sourceTool = (string) ($payload['source_tool'] ?? '');

            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            // لا نملأ حقل الأداة من قيمة أنتجتها الأداة نفسها.
            if ($sourceTool === $tool->code) {
                continue;
            }

            foreach (self::CONSUMES[$canonicalKey] as $targetField) {
                if (! isset($fieldSet[$targetField]) || isset($suggestions[$targetField])) {
                    continue;
                }

                $suggestions[$targetField] = [
                    'value' => Str::limit(trim($value), 220, ''),
                    'source' => 'derived_from_tool',
                    'label' => 'مأخوذ من '.$this->toolLabel($sourceTool),
                ];
            }
        }

        return $suggestions;
    }

    private function toolLabel(string $code): string
    {
        if ($code === '') {
            return 'أداة سابقة';
        }

        if ($code === 'founder-interview') {
            return 'مقابلة التعريف';
        }

        return $this->toolNameCache[$code] ??= (string) (
            Tool::query()->where('code', $code)->value('name') ?: $code
        );
    }
}
