<?php

namespace App\Console\Commands;

use App\Domain\AI\Knowledge\Models\KnowledgeQueryEmbedding;
use Illuminate\Console\Command;

class MaintainKnowledgeEmbeddingsCommand extends Command
{
    protected $signature = 'knowledge:maintain-embeddings';

    protected $description = 'Remove expired query vectors while preserving versioned chunk embeddings';

    public function handle(): int
    {
        $removed = KnowledgeQueryEmbedding::query()->where('expires_at', '<=', now())->delete();
        $this->line("Expired query embeddings removed: {$removed}");

        return self::SUCCESS;
    }
}
