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
        $activeChunks = DB::table('knowledge_chunks')
            ->join('knowledge_documents', 'knowledge_documents.id', '=', 'knowledge_chunks.knowledge_document_id')
            ->where('knowledge_documents.status', 'active')
            ->count();
        $activeEmbeddings = DB::table('knowledge_embeddings')
            ->join('knowledge_chunks', 'knowledge_chunks.id', '=', 'knowledge_embeddings.knowledge_chunk_id')
            ->join('knowledge_documents', 'knowledge_documents.id', '=', 'knowledge_chunks.knowledge_document_id')
            ->where('knowledge_embeddings.status', 'active')
            ->where('knowledge_documents.status', 'active')
            ->distinct()
            ->count('knowledge_embeddings.knowledge_chunk_id');
        $latestEvaluation = DB::table('intelligence_evaluation_runs')->orderByDesc('completed_at')->orderByDesc('id')->first();
        $metrics = [
            'sources' => DB::table('knowledge_sources')->count(),
            'documents' => DB::table('knowledge_documents')->count(),
            'chunks' => DB::table('knowledge_chunks')->count(),
            'uploads' => DB::table('knowledge_uploads')->count(),
            'uploads_indexed' => DB::table('knowledge_uploads')->where('status', 'indexed')->count(),
            'uploads_stored' => DB::table('knowledge_uploads')->where('status', 'stored')->count(),
            'uploads_failed' => DB::table('knowledge_uploads')->where('status', 'failed')->count(),
            'uploads_needing_worker' => DB::table('knowledge_uploads')->where('status', 'needs_worker')->count(),
            'structured_file_uploads' => DB::table('knowledge_uploads')
                ->whereIn('extension', ['pdf', 'docx', 'xlsx', 'png', 'jpg', 'jpeg', 'webp', 'tif', 'tiff'])
                ->count(),
            'ocr_uploads_indexed' => DB::table('knowledge_uploads')
                ->where('mime_type', 'like', 'image/%')
                ->where('status', 'indexed')
                ->count(),
            'open_upload_sessions' => DB::table('knowledge_upload_sessions')
                ->where('status', 'open')
                ->where('expires_at', '>=', now())
                ->count(),
            'expired_upload_sessions' => DB::table('knowledge_upload_sessions')
                ->where('status', 'open')
                ->where('expires_at', '<', now())
                ->count(),
            'unlinked_uploads' => DB::table('knowledge_uploads')
                ->where('status', 'indexed')
                ->whereNull('knowledge_source_id')
                ->count(),
            'candidate_claims' => DB::table('knowledge_claims')->where('review_status', 'candidate')->count(),
            'failed_jobs' => DB::table('intelligence_jobs')->where('status', 'failed')->count(),
            'queued_worker_jobs' => DB::table('intelligence_jobs')->where('status', 'queued')->count(),
            'leased_worker_jobs' => DB::table('intelligence_jobs')->where('status', 'leased')->count(),
            'active_workers' => DB::table('intelligence_workers')->where('status', 'active')->count(),
            'online_workers' => DB::table('intelligence_workers')
                ->where('status', 'active')
                ->where('last_seen_at', '>=', now()->subMinutes(5))
                ->count(),
            'pending_reconciliations' => DB::table('knowledge_sources')
                ->whereNotNull('meta_json')
                ->where('meta_json', 'like', '%"legacy_pending_generation"%')
                ->count(),
            'stale_documents' => DB::table('knowledge_documents')
                ->where('status', 'active')
                ->whereNotNull('valid_until')
                ->where('valid_until', '<', now())
                ->count(),
            'active_embeddings' => $activeEmbeddings,
            'embedding_coverage_percent' => $activeChunks > 0 ? round(($activeEmbeddings / $activeChunks) * 100, 2) : 0,
            'stale_embeddings' => DB::table('knowledge_embeddings')
                ->join('knowledge_chunks', 'knowledge_chunks.id', '=', 'knowledge_embeddings.knowledge_chunk_id')
                ->join('knowledge_documents', 'knowledge_documents.id', '=', 'knowledge_chunks.knowledge_document_id')
                ->where(fn ($query) => $query
                    ->where('knowledge_embeddings.status', '!=', 'active')
                    ->orWhere('knowledge_documents.status', '!=', 'active'))
                ->count(),
            'pending_embedding_jobs' => DB::table('intelligence_jobs')->where('type', 'embeddings')->whereIn('status', ['queued', 'leased'])->count(),
            'latest_evaluation_status' => $latestEvaluation?->status ?? 'none',
            'latest_evaluation_recall' => $latestEvaluation !== null ? (float) $latestEvaluation->recall_at_k : null,
            'latest_evaluation_mrr' => $latestEvaluation !== null ? (float) $latestEvaluation->mean_reciprocal_rank : null,
            'web_sources' => DB::table('knowledge_sources')->where('kind', 'web_page')->count(),
            'web_results' => DB::table('web_research_results')->count(),
            'web_results_verified' => DB::table('web_research_results')->where('verification_status', 'verified')->count(),
            'web_results_unverified' => DB::table('web_research_results')->where('verification_status', 'unverified')->count(),
            'web_results_conflict' => DB::table('web_research_results')->where('verification_status', 'conflict')->count(),
            'web_fetch_failures' => DB::table('web_research_results')->where('fetch_status', 'failed')->count(),
            'web_sources_due_refresh' => DB::table('knowledge_documents')
                ->join('knowledge_sources', 'knowledge_sources.id', '=', 'knowledge_documents.knowledge_source_id')
                ->where('knowledge_sources.kind', 'web_page')
                ->where('knowledge_documents.status', 'active')
                ->whereNotNull('knowledge_documents.valid_until')
                ->where('knowledge_documents.valid_until', '<=', now())
                ->distinct()
                ->count('knowledge_sources.id'),
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
