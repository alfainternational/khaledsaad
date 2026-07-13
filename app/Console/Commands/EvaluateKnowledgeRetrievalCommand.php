<?php

namespace App\Console\Commands;

use App\Domain\AI\Knowledge\KnowledgeRetriever;
use App\Domain\AI\Knowledge\KnowledgeScope;
use App\Domain\AI\Knowledge\Models\IntelligenceEvaluationCase;
use App\Domain\AI\Knowledge\Models\IntelligenceEvaluationRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EvaluateKnowledgeRetrievalCommand extends Command
{
    protected $signature = 'knowledge:evaluate-retrieval {--strict : Return failure when quality is below configured thresholds}';

    protected $description = 'Measure and persist structured knowledge retrieval quality';

    public function handle(KnowledgeRetriever $retriever): int
    {
        $cases = IntelligenceEvaluationCase::query()->where('status', 'active')->orderBy('id')->limit(500)->get();
        if ($cases->isEmpty()) {
            $this->warn('No active retrieval evaluation cases.');

            return $this->option('strict') ? self::FAILURE : self::SUCCESS;
        }
        $engine = (bool) config('services.knowledge.hybrid_retrieval', false) ? 'hybrid' : 'lexical';
        $started = hrtime(true);
        $rows = [];
        foreach ($cases as $case) {
            try {
                $scope = new KnowledgeScope($case->account_id, $case->workspace_id, $case->project_id, $case->visibility);
            } catch (\InvalidArgumentException) {
                $this->error("Evaluation case {$case->public_id} has an invalid scope.");

                return self::FAILURE;
            }
            if (! hash_equals($scope->key(), $case->scope_key)) {
                $this->error("Evaluation case {$case->public_id} has a mismatched scope key.");

                return self::FAILURE;
            }
            $caseStarted = hrtime(true);
            $evidence = $retriever->retrieve($scope, $case->query, max(1, min(50, $case->minimum_rank)));
            $rank = null;
            foreach ($evidence->values() as $index => $item) {
                if (($case->expected_chunk_id !== null && $item->chunkId === $case->expected_chunk_id)
                    || ($case->expected_chunk_id === null && is_string($case->expected_source_uri) && $item->sourceUri === $case->expected_source_uri)) {
                    $rank = $index + 1;
                    break;
                }
            }
            $rows[] = [
                'case' => $case,
                'rank' => $rank,
                'reciprocal_rank' => $rank ? 1 / $rank : 0.0,
                'latency_ms' => (int) round((hrtime(true) - $caseStarted) / 1_000_000),
                'passed' => $rank !== null && $rank <= $case->minimum_rank,
                'diagnostics_json' => ['returned_chunk_ids' => $evidence->pluck('chunkId')->all()],
            ];
        }
        $recall = collect($rows)->where('passed', true)->count() / count($rows);
        $mrr = (float) collect($rows)->avg('reciprocal_rank');
        $latency = (int) round((hrtime(true) - $started) / 1_000_000);
        $passed = $recall >= (float) config('services.knowledge.evaluation_min_recall', 0.8)
            && $mrr >= (float) config('services.knowledge.evaluation_min_mrr', 0.6);

        DB::transaction(function () use ($rows, $engine, $recall, $mrr, $latency, $passed): void {
            $run = IntelligenceEvaluationRun::query()->create([
                'public_id' => (string) Str::uuid(),
                'engine' => $engine,
                'model_name' => $engine === 'hybrid' ? config('services.knowledge.embedding_model') : null,
                'model_version' => $engine === 'hybrid' ? config('services.knowledge.embedding_model_version') : null,
                'case_count' => count($rows),
                'recall_at_k' => $recall,
                'mean_reciprocal_rank' => $mrr,
                'latency_ms' => $latency,
                'status' => $passed ? 'passed' : 'failed',
                'completed_at' => now(),
            ]);
            foreach ($rows as $row) {
                $run->results()->create([
                    'intelligence_evaluation_case_id' => $row['case']->id,
                    'rank' => $row['rank'],
                    'reciprocal_rank' => $row['reciprocal_rank'],
                    'latency_ms' => $row['latency_ms'],
                    'passed' => $row['passed'],
                    'diagnostics_json' => $row['diagnostics_json'],
                ]);
            }
        });

        $this->line(json_encode(['engine' => $engine, 'cases' => count($rows), 'recall_at_k' => round($recall, 6), 'mrr' => round($mrr, 6), 'latency_ms' => $latency, 'status' => $passed ? 'passed' : 'failed'], JSON_THROW_ON_ERROR));

        return $this->option('strict') && ! $passed ? self::FAILURE : self::SUCCESS;
    }
}
