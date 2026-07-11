<?php

namespace App\Console\Commands;

use App\Domain\AI\Knowledge\Models\KnowledgeUpload;
use App\Domain\AI\Knowledge\Uploads\KnowledgeUploadIndexer;
use Illuminate\Console\Command;
use Throwable;

class ProcessKnowledgeUploadsCommand extends Command
{
    protected $signature = 'knowledge:process-uploads {--limit=20}';

    protected $description = 'Resume stored knowledge uploads that were not indexed during their request';

    public function handle(KnowledgeUploadIndexer $indexer): int
    {
        $limit = (int) $this->option('limit');
        if ($limit < 1 || $limit > 100) {
            $this->error('The upload processing limit must be between 1 and 100.');

            return self::INVALID;
        }

        $indexed = 0;
        $failed = 0;
        KnowledgeUpload::query()
            ->where('status', 'stored')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (KnowledgeUpload $upload) use ($indexer, &$indexed, &$failed): void {
                try {
                    $indexer->index($upload);
                    $indexed++;
                } catch (Throwable) {
                    $failed++;
                }
            });

        $this->line("Indexed: {$indexed}; failed: {$failed}");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
