<?php

namespace App\Console\Commands;

use App\Domain\AI\Knowledge\InlineChunkEmbedder;
use App\Domain\AI\Knowledge\Models\KnowledgeQueryEmbedding;
use Illuminate\Console\Command;

class MaintainKnowledgeEmbeddingsCommand extends Command
{
    protected $signature = 'knowledge:maintain-embeddings {--limit=200 : أقصى عدد مقاطع تُضمَّن في التشغيلة}';

    protected $description = 'Remove expired query vectors and embed pending chunks inline when the API producer is active';

    public function handle(InlineChunkEmbedder $embedder): int
    {
        $removed = KnowledgeQueryEmbedding::query()->where('expires_at', '<=', now())->delete();
        $this->line("Expired query embeddings removed: {$removed}");

        // المسار المضمّن: تضمين المقاطع المعلّقة من الخادم مباشرة (بلا عامل).
        $result = $embedder->embedPending(max(1, (int) $this->option('limit')));
        $this->line("Inline chunk embeddings: {$result['embedded']} embedded, {$result['failed']} failed");

        return self::SUCCESS;
    }
}
