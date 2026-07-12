<?php

namespace App\Console\Commands;

use App\Domain\AI\Knowledge\KnowledgeScope;
use App\Domain\AI\Knowledge\Models\IntelligenceEvaluationCase;
use App\Domain\AI\Knowledge\Models\KnowledgeSource;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SyncKnowledgeEvaluationCasesCommand extends Command
{
    protected $signature = 'knowledge:sync-evaluation-cases';

    protected $description = 'Synchronize curated retrieval evaluation cases with active knowledge sources';

    public function handle(): int
    {
        $scope = KnowledgeScope::global();
        $synced = 0;
        $missing = 0;
        foreach ((array) config('knowledge_evaluation.cases', []) as $definition) {
            if (! is_array($definition) || ! is_string($definition['query'] ?? null) || ! is_string($definition['expected_source_uri'] ?? null)) {
                $missing++;

                continue;
            }
            $source = KnowledgeSource::query()
                ->inScope($scope)
                ->where('canonical_uri', $definition['expected_source_uri'])
                ->with(['documents' => fn ($query) => $query->where('status', 'active')->with(['chunks' => fn ($chunks) => $chunks->orderBy('position')])])
                ->first();
            $document = $source?->documents->first();
            $chunk = $document?->chunks->first();
            if (! $chunk) {
                $missing++;

                continue;
            }
            $case = IntelligenceEvaluationCase::query()->firstOrNew(
                ['scope_key' => $scope->key(), 'query' => trim($definition['query'])],
            );
            if (! $case->exists) {
                $case->public_id = (string) Str::uuid();
            }
            $case->fill([
                'account_id' => null,
                'workspace_id' => null,
                'project_id' => null,
                'visibility' => 'global',
                'expected_chunk_id' => $chunk->id,
                'expected_source_uri' => $source->canonical_uri,
                'minimum_rank' => max(1, min(50, (int) ($definition['minimum_rank'] ?? 5))),
                'status' => 'active',
                'meta_json' => ['origin' => 'curated_config'],
            ])->save();
            $synced++;
        }
        $this->line("Evaluation cases synced: {$synced}; missing sources: {$missing}");

        return $missing > 0 ? self::FAILURE : self::SUCCESS;
    }
}
