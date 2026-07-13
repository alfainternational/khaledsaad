<?php

namespace App\Console\Commands;

use App\Domain\AI\Knowledge\Models\KnowledgeUploadSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupKnowledgeUploadSessionsCommand extends Command
{
    protected $signature = 'knowledge:cleanup-upload-sessions {--limit=100}';

    protected $description = 'Remove expired resumable knowledge upload sessions and their private chunks';

    public function handle(): int
    {
        $limit = max(1, min(1000, (int) $this->option('limit')));
        $removed = 0;
        KnowledgeUploadSession::query()
            ->where('status', 'open')
            ->where('expires_at', '<', now())
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (KnowledgeUploadSession $session) use (&$removed): void {
                Storage::disk($session->disk)->deleteDirectory($session->path);
                $session->delete();
                $removed++;
            });

        $this->line("Removed: {$removed}");

        return self::SUCCESS;
    }
}
