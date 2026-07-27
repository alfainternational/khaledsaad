<?php

namespace App\Services\Projects;

use App\Models\Project;
use App\Models\ProjectAnswer;
use App\Models\ProjectKnowledgeSource;
use Illuminate\Support\Facades\DB;

class ProjectKnowledgeService
{
    /** @param array<string,mixed> $metadata */
    public function record(
        Project $project,
        string $fieldKey,
        mixed $value,
        string $sourceType,
        ?string $sourceKey = null,
        ?int $sourceId = null,
        string $confidence = 'medium',
        ?string $period = null,
        array $metadata = [],
    ): ?ProjectAnswer {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        return DB::transaction(function () use ($project, $fieldKey, $value, $sourceType, $sourceKey, $sourceId, $confidence, $period, $metadata): ProjectAnswer {
            $answer = ProjectAnswer::updateOrCreate(
                ['project_id' => $project->id, 'field_key' => $fieldKey],
                [
                    'value_json' => ['value' => $value],
                    'source_tool_key' => $sourceType === 'consultation' ? 'consultation' : ($sourceType === 'tool' ? $sourceKey : null),
                    'source_run_id' => $sourceType === 'tool' ? $sourceId : null,
                ],
            );

            $encoded = json_encode(['value' => $value], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            ProjectKnowledgeSource::create([
                'project_id' => $project->id,
                'field_key' => $fieldKey,
                'value_json' => ['value' => $value],
                'value_hash' => hash('sha256', (string) $encoded),
                'event_type' => 'asserted',
                'source_type' => $sourceType,
                'source_key' => $sourceKey,
                'source_id' => $sourceId,
                'confidence' => $confidence,
                'period' => $period,
                'metadata' => $metadata,
                'recorded_at' => now(),
            ]);

            return $answer;
        });
    }

    /** @param array<string,mixed> $metadata */
    public function retract(
        Project $project,
        string $fieldKey,
        string $sourceType,
        ?string $sourceKey = null,
        ?int $sourceId = null,
        array $metadata = [],
    ): void {
        DB::transaction(function () use ($project, $fieldKey, $sourceType, $sourceKey, $sourceId, $metadata): void {
            ProjectAnswer::where('project_id', $project->id)->where('field_key', $fieldKey)->delete();
            ProjectKnowledgeSource::create([
                'project_id' => $project->id,
                'field_key' => $fieldKey,
                'value_json' => null,
                'value_hash' => null,
                'event_type' => 'retracted',
                'source_type' => $sourceType,
                'source_key' => $sourceKey,
                'source_id' => $sourceId,
                'confidence' => 'high',
                'metadata' => $metadata,
                'recorded_at' => now(),
            ]);
        });
    }
}
