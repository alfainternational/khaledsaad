<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class KnowledgeHealthCommand extends Command
{
    protected $signature = 'knowledge:health {--json}';

    protected $description = 'Report read-only health metrics for the structured knowledge foundation';

    public function handle(): int
    {
        $metrics = [
            'sources' => DB::table('knowledge_sources')->count(),
            'documents' => DB::table('knowledge_documents')->count(),
            'chunks' => DB::table('knowledge_chunks')->count(),
            'candidate_claims' => DB::table('knowledge_claims')->where('review_status', 'candidate')->count(),
            'failed_jobs' => DB::table('intelligence_jobs')->where('status', 'failed')->count(),
            'pending_reconciliations' => DB::table('knowledge_sources')
                ->whereNotNull('meta_json')
                ->where('meta_json', 'like', '%"legacy_pending_generation"%')
                ->count(),
            'stale_documents' => DB::table('knowledge_documents')
                ->where('status', 'active')
                ->whereNotNull('valid_until')
                ->where('valid_until', '<', now())
                ->count(),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($metrics, JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        foreach ($metrics as $metric => $value) {
            $this->line($metric.': '.$value);
        }

        return self::SUCCESS;
    }
}
