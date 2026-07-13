<?php

namespace App\Domain\AI\Knowledge;

use App\Domain\Project\Models\Project;
use Illuminate\Support\Collection;

class KnowledgePromptContext
{
    public function __construct(private readonly KnowledgeRetriever $retriever) {}

    /** @return array{evidence: list<array<string, mixed>>, prompt_block: string} */
    public function forProject(Project $project, string $query, int $limit = 8): array
    {
        $project->loadMissing('workspace');
        $scope = KnowledgeScope::fromProject($project);
        $evidence = $this->retriever->retrieve($scope, $query, $limit);

        return [
            'evidence' => $evidence->map->toArray()->all(),
            'prompt_block' => $this->format($evidence),
        ];
    }

    /** @param Collection<int, KnowledgeEvidence> $evidence */
    private function format(Collection $evidence): string
    {
        if ($evidence->isEmpty()) {
            return '';
        }

        $lines = [
            '=== أدلة قاعدة المعرفة ===',
            'استخدم الأدلة التالية عند صلتها بالسؤال. ألحق رمز الاستشهاد بكل ادعاء مبني على مصدر.',
            'ميّز بوضوح بين الدليل والاستنتاج، ولا تخترع حقيقة عند غياب الدليل.',
        ];

        foreach ($evidence as $item) {
            $locator = $item->locator === []
                ? ''
                : ' | الموضع: '.json_encode($item->locator, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $lines[] = implode(' ', [
                $item->citation,
                'المصدر: '.$item->sourceTitle,
                '| النوع: '.$item->sourceKind,
                '| الثقة: '.$item->trustScore.'/100'.$locator,
            ]);
            $lines[] = $item->excerpt;
        }

        return implode("\n", $lines);
    }
}
