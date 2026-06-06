<?php

namespace App\Domain\AI\Kernel\Memory;

use App\Domain\AI\Kernel\AgentContext;
use App\Domain\WorkspaceData\Models\WorkspaceData;

/**
 * استرجاع الذاكرة ذات الصلة وقت الطلب — بلا قاعدة متجهات ولا فهرسة خلفية.
 *
 * الفكرة الثورية الخفيفة (مستلهَمة من memdir/findRelevantMemories في cloud):
 * الذاكرة = مخرجات الأدوات المخزّنة فعلاً في workspace_data. نحسب الصلة لحظياً
 * بتداخل كلمات بسيط (يمكن لاحقاً ترقيته إلى MySQL FULLTEXT) ونعيد الأعلى صلة.
 * تكلفة شبه صفرية، تعمل على أي استضافة.
 */
class MemoryScanner
{
    /**
     * @return array<int, array{key: string, value: mixed, score: int}>
     */
    public function relevant(AgentContext $context, int $limit = 6): array
    {
        if ($context->workspace === null) {
            return [];
        }

        $rows = WorkspaceData::query()
            ->where('workspace_id', $context->workspace->getKey())
            ->when($context->project !== null, fn ($q) => $q->where(function ($q) use ($context) {
                $q->where('project_id', $context->project->getKey())->orWhereNull('project_id');
            }))
            ->latest('updated_at')
            ->limit(50)
            ->get(['key', 'value_json', 'project_id']);

        $terms = $this->terms($context->intent.' '.implode(' ', array_map(
            fn ($v): string => is_scalar($v) ? (string) $v : '',
            $context->signals,
        )));

        $scored = $rows->map(function (WorkspaceData $row) use ($terms): array {
            $haystack = mb_strtolower($row->key.' '.json_encode($row->value_json, JSON_UNESCAPED_UNICODE));
            $score = 0;
            foreach ($terms as $term) {
                if ($term !== '' && mb_strpos($haystack, $term) !== false) {
                    $score++;
                }
            }

            return [
                'key' => (string) $row->key,
                'value' => $row->value_json,
                'score' => $score,
            ];
        });

        return $scored
            ->sortByDesc('score')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function terms(string $text): array
    {
        $text = mb_strtolower(preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? '');

        return collect(preg_split('/\s+/u', $text) ?: [])
            ->filter(fn (string $w): bool => mb_strlen($w) >= 3)
            ->unique()
            ->take(20)
            ->values()
            ->all();
    }
}
