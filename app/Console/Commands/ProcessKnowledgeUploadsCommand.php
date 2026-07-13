<?php

namespace App\Console\Commands;

use App\Domain\AI\Knowledge\Models\KnowledgeUpload;
use App\Domain\AI\Knowledge\Uploads\KnowledgeUploadIndexer;
use App\Domain\AI\Knowledge\Uploads\TextKnowledgeExtractor;
use App\Domain\AI\Worker\KnowledgeUploadJobDispatcher;
use Illuminate\Console\Command;
use Throwable;

class ProcessKnowledgeUploadsCommand extends Command
{
    protected $signature = 'knowledge:process-uploads {--limit=20}';

    protected $description = 'Resume stored knowledge uploads that were not indexed during their request';

    public function handle(
        KnowledgeUploadIndexer $indexer,
        KnowledgeUploadJobDispatcher $dispatcher,
        TextKnowledgeExtractor $extractor,
    ): int {
        $limit = (int) $this->option('limit');
        if ($limit < 1 || $limit > 100) {
            $this->error('The upload processing limit must be between 1 and 100.');

            return self::INVALID;
        }

        $indexed = 0;
        $queued = 0;
        $failed = 0;
        KnowledgeUpload::query()
            ->where('status', 'stored')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (KnowledgeUpload $upload) use ($indexer, $dispatcher, $extractor, &$indexed, &$queued, &$failed): void {
                try {
                    if (! $extractor->supports($upload->mime_type)) {
                        $dispatcher->dispatch($upload);
                        $queued++;

                        return;
                    }

                    $indexer->index($upload);
                    $indexed++;
                } catch (Throwable) {
                    $failed++;
                }
            });

        $this->line("Indexed: {$indexed}; queued: {$queued}; failed: {$failed}");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
