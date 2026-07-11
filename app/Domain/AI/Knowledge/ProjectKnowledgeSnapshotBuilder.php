<?php

namespace App\Domain\AI\Knowledge;

use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\ToolRun;
use App\Domain\WorkspaceData\Models\WorkspaceData;

class ProjectKnowledgeSnapshotBuilder
{
    /**
     * @return array{title: string, content: string, chunks: array<int, array{heading: string, content: string, locator: array<string, string>}>}
     */
    public function build(Project $project): array
    {
        if ($project->id === null || $project->workspace_id === null || trim((string) $project->public_id) === '') {
            throw new InvalidProjectKnowledgeData('Project snapshot identity is incomplete.');
        }

        $brief = $this->marketingBrief($project);
        $sections = [
            'Project' => $this->present([
                'name' => $project->name,
                'stage' => $project->stage,
                'status' => $project->status,
                'summary' => data_get($brief, 'business.summary'),
                'offer' => data_get($brief, 'business.offer'),
                'ideal_customer' => data_get($brief, 'audience.ideal_customer'),
                'positioning_edge' => data_get($brief, 'positioning.edge'),
            ]),
            'Market' => $this->present([
                'sector' => $project->sector,
                'country' => $project->market_country,
                'primary_domain' => $project->primary_domain,
                'market' => data_get($brief, 'business.market'),
                'pain_points' => data_get($brief, 'audience.pain_points'),
                'buying_trigger' => data_get($brief, 'audience.buying_trigger'),
            ]),
            'Channels' => $this->present([
                'official_social_links' => $this->sanitize($project->official_social_links_json),
                'verified_social_profiles' => $this->sanitize($project->verified_social_profiles_json),
                'current_channels' => data_get($brief, 'current_marketing.channels'),
                'current_state' => data_get($brief, 'current_marketing.current_state'),
                'assets' => data_get($brief, 'current_marketing.assets'),
                'brand_voice' => data_get($brief, 'brand.voice'),
                'tone_rules' => data_get($brief, 'brand.tone_rules'),
            ]),
            'Competitors' => $this->present([
                'competitors' => $this->sanitize($project->competitors_json),
                'brief_competitors' => data_get($brief, 'competition.competitors'),
                'market_gap' => data_get($brief, 'competition.gap'),
            ]),
            'Goals' => $this->present([
                'analysis_goals' => $this->sanitize($project->analysis_goals_json),
                'primary_goal' => data_get($brief, 'goals.primary_goal'),
                'success_metric' => data_get($brief, 'goals.success_metric'),
                'timeframe' => data_get($brief, 'goals.timeframe'),
                'execution_priority' => data_get($brief, 'execution.priority'),
                'next_asset' => data_get($brief, 'execution.next_asset'),
                'tool_summaries' => $this->toolSummaries($project),
            ]),
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

        $title = preg_replace('/[\t ]+|\n+/', ' ', trim($this->normalizeLineEndings((string) $project->name)))
            ?? trim((string) $project->name);

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

        return is_array($payload) ? $this->sanitize($payload) : [];
    }

    /** @return array<string, mixed> */
    private function toolSummaries(Project $project): array
    {
        $latestRunIds = ToolRun::query()
            ->selectRaw('MAX(id)')
            ->where('workspace_id', $project->workspace_id)
            ->where('project_id', $project->id)
            ->groupBy('tool_code');

        return ToolRun::query()
            ->where('workspace_id', $project->workspace_id)
            ->where('project_id', $project->id)
            ->whereIn('id', $latestRunIds)
            ->orderBy('tool_code')
            ->get(['id', 'tool_code', 'summary_json', 'next_actions_json'])
            ->mapWithKeys(function (ToolRun $run): array {
                $summary = $this->sanitize([
                    'summary' => $run->summary_json,
                    'next_actions' => $run->next_actions_json,
                ]);

                return $summary === [] ? [] : [$run->tool_code => $summary];
            })
            ->all();
    }

    private function sanitize(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->redactSensitiveUrlQuery($this->normalizeLineEndings($value));
        }

        if (! is_array($value)) {
            return $value;
        }

        $clean = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                continue;
            }

            $clean[$key] = $this->sanitize($item);
        }

        return $clean;
    }

    private function isSensitiveKey(string $key): bool
    {
        return preg_match(
            '/(?:authorization|private[_-]?key|access[_-]?key|api[_-]?key|cookies?|tokens?|pass(?:word|wd)s?|secrets?|credentials?|signatures?)/i',
            $key,
        ) === 1;
    }

    private function redactSensitiveUrlQuery(string $value): string
    {
        if (! str_contains($value, '?')) {
            return $value;
        }

        return preg_replace_callback(
            '/([?&])([^=&#]+)=([^&#]*)/',
            fn (array $match): string => $this->isSensitiveKey(rawurldecode($match[2]))
                ? $match[1].$match[2].'=[REDACTED]'
                : $match[0],
            $value,
        ) ?? $value;
    }

    /**
     * @param  array<mixed>  $values
     * @return list<string>
     */
    private function flatten(array $values, string $prefix = ''): array
    {
        if (! array_is_list($values)) {
            ksort($values, SORT_STRING);
        }

        $lines = [];
        foreach ($values as $key => $value) {
            $segment = $this->escapePathSegment((string) $key);
            $path = $prefix === '' ? $segment : $prefix.'.'.$segment;

            if (is_array($value)) {
                if ($value !== []) {
                    $lines = array_merge($lines, $this->flatten($value, $path));
                }

                continue;
            }

            if ((is_scalar($value) || $value === null) && (! is_string($value) || trim($value) !== '')) {
                $lines[] = $path.': '.json_encode(
                    is_string($value) ? $this->normalizeLineEndings($value) : $value,
                    JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
                );
            }
        }

        return $lines;
    }

    /** @param array<string, mixed> $values */
    private function present(array $values): array
    {
        return array_filter(
            $values,
            fn (mixed $value): bool => $value !== null && $value !== [] && (! is_string($value) || trim($value) !== ''),
        );
    }

    private function escapePathSegment(string $segment): string
    {
        $segment = str_replace('~', '~0', $segment);
        $segment = str_replace('.', '~1', $segment);

        return preg_replace_callback(
            '/[\x00-\x1F\x7F]/',
            fn (array $match): string => sprintf('~u%04X', ord($match[0])),
            $segment,
        ) ?? $segment;
    }

    private function normalizeLineEndings(string $value): string
    {
        return str_replace(["\r\n", "\r"], "\n", $value);
    }
}
