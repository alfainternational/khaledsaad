<?php

namespace App\Domain\AI\Knowledge;

use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\ToolRun;
use App\Domain\WorkspaceData\Models\WorkspaceData;
use UnexpectedValueException;

class ProjectKnowledgeSnapshotBuilder
{
    /**
     * @return array{title: string, content: string, chunks: array<int, array{heading: string, content: string, locator: array<string, string>}>}
     */
    public function build(Project $project): array
    {
        if ($project->id === null || $project->workspace_id === null || trim((string) $project->public_id) === '') {
            throw new UnexpectedValueException('Project snapshot identity is incomplete.');
        }

        $brief = $this->marketingBrief($project);
        $toolSummaries = $this->toolSummaries($project);
        $sections = [
            'Project' => [
                'name' => $project->name,
                'stage' => $project->stage,
                'status' => $project->status,
                'summary' => data_get($brief, 'business.summary'),
                'offer' => data_get($brief, 'business.offer'),
                'ideal_customer' => data_get($brief, 'audience.ideal_customer'),
                'positioning_edge' => data_get($brief, 'positioning.edge'),
            ],
            'Market' => [
                'sector' => $project->sector,
                'country' => $project->market_country,
                'primary_domain' => $project->primary_domain,
                'market' => data_get($brief, 'business.market'),
                'pain_points' => data_get($brief, 'audience.pain_points'),
                'buying_trigger' => data_get($brief, 'audience.buying_trigger'),
            ],
            'Channels' => [
                'official_social_links' => $project->official_social_links_json,
                'verified_social_profiles' => $project->verified_social_profiles_json,
                'current_channels' => data_get($brief, 'current_marketing.channels'),
                'current_state' => data_get($brief, 'current_marketing.current_state'),
                'assets' => data_get($brief, 'current_marketing.assets'),
                'brand_voice' => data_get($brief, 'brand.voice'),
                'tone_rules' => data_get($brief, 'brand.tone_rules'),
            ],
            'Competitors' => [
                'competitors' => $project->competitors_json,
                'brief_competitors' => data_get($brief, 'competition.competitors'),
                'market_gap' => data_get($brief, 'competition.gap'),
            ],
            'Goals' => [
                'analysis_goals' => $project->analysis_goals_json,
                'primary_goal' => data_get($brief, 'goals.primary_goal'),
                'success_metric' => data_get($brief, 'goals.success_metric'),
                'timeframe' => data_get($brief, 'goals.timeframe'),
                'execution_priority' => data_get($brief, 'execution.priority'),
                'next_asset' => data_get($brief, 'execution.next_asset'),
                'tool_summaries' => $toolSummaries,
            ],
        ];

        $chunks = [];
        foreach ($sections as $heading => $values) {
            $field = strtolower($heading);
            $lines = $this->flatten($values);
            $chunks[] = [
                'heading' => $heading,
                'content' => $lines === [] ? "section: {$field}" : implode("\n", $lines),
                'locator' => ['field' => $field],
            ];
        }

        $title = $this->normalizeText((string) $project->name);

        return [
            'title' => $title,
            'content' => implode("\n\n", array_map(
                fn (array $chunk): string => $chunk['heading']."\n".$chunk['content'],
                $chunks,
            )),
            'chunks' => $chunks,
        ];
    }

    /** @return array<string, mixed> */
    private function marketingBrief(Project $project): array
    {
        $payload = WorkspaceData::query()
            ->where('workspace_id', $project->workspace_id)
            ->where('project_id', $project->id)
            ->where('key', 'project.marketing_brief')
            ->value('value_json');

        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }

        return is_array($payload) ? $payload : [];
    }

    /** @return array<string, mixed> */
    private function toolSummaries(Project $project): array
    {
        return ToolRun::query()
            ->where('workspace_id', $project->workspace_id)
            ->where('project_id', $project->id)
            ->whereNotNull('summary_json')
            ->orderBy('tool_code')
            ->orderByDesc('id')
            ->get(['id', 'tool_code', 'summary_json', 'next_actions_json'])
            ->unique('tool_code')
            ->mapWithKeys(function (ToolRun $run): array {
                $summary = $this->removeSensitiveKeys([
                    'summary' => $run->summary_json,
                    'next_actions' => $run->next_actions_json,
                ]);

                return $summary === [] ? [] : [$run->tool_code => $summary];
            })
            ->all();
    }

    /** @return array<mixed> */
    private function removeSensitiveKeys(array $payload): array
    {
        $clean = [];

        foreach ($payload as $key => $value) {
            if (preg_match('/(?:password|secret|token|credential|api[_-]?key)/i', (string) $key) === 1) {
                continue;
            }

            if (is_array($value)) {
                $value = $this->removeSensitiveKeys($value);
            }

            if ($value !== null && $value !== [] && (! is_string($value) || trim($value) !== '')) {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return list<string>
     */
    private function flatten(array $values, string $prefix = ''): array
    {
        ksort($values, SORT_STRING);
        $lines = [];

        foreach ($values as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                if ($value === []) {
                    continue;
                }

                if (array_is_list($value)) {
                    foreach ($value as $index => $item) {
                        if ($this->isFilledScalar($item)) {
                            $lines[] = $path.'.'.$index.': '.$this->normalizeText((string) $item);
                        }
                    }
                } else {
                    $lines = array_merge($lines, $this->flatten($value, $path));
                }

                continue;
            }

            if ($this->isFilledScalar($value)) {
                $lines[] = $path.': '.$this->normalizeText(is_bool($value) ? ($value ? 'true' : 'false') : (string) $value);
            }
        }

        return $lines;
    }

    private function isFilledScalar(mixed $value): bool
    {
        return (is_scalar($value) || $value === null)
            && $value !== null
            && (! is_string($value) || trim($value) !== '');
    }

    private function normalizeText(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", trim($value));

        return preg_replace('/[\t ]+|\n+/', ' ', $value) ?? $value;
    }
}
