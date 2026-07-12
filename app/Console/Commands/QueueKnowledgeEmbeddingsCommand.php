<?php

namespace App\Console\Commands;

use App\Domain\AI\Knowledge\EmbeddingJobDispatcher;
use Illuminate\Console\Command;

class QueueKnowledgeEmbeddingsCommand extends Command
{
    protected $signature = 'knowledge:queue-embeddings {--limit=100 : Maximum chunks inspected per run}';

    protected $description = 'Queue bounded private-worker jobs for missing or stale knowledge embeddings';

    public function handle(EmbeddingJobDispatcher $dispatcher): int
    {
        if (! (bool) config('services.private_worker.enabled', false)) {
            $this->warn('Private worker is disabled; no embedding jobs were queued.');

            return self::SUCCESS;
        }

        $created = $dispatcher->dispatch((int) $this->option('limit'));
        $this->info("Queued {$created} embedding job(s).");

        return self::SUCCESS;
    }
}
