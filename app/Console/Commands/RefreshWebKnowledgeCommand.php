<?php

namespace App\Console\Commands;

use App\Domain\AI\Web\WebKnowledgeRefresher;
use Illuminate\Console\Command;

class RefreshWebKnowledgeCommand extends Command
{
    protected $signature = 'knowledge:refresh-web {--limit=10 : Maximum due sources} {--deadline=40 : Runtime deadline in seconds}';

    protected $description = 'Refresh a bounded batch of due public web knowledge';

    public function handle(WebKnowledgeRefresher $refresher): int
    {
        if (! (bool) config('services.web_search.scheduled_refresh', false)) {
            $this->warn('Scheduled web research refresh is disabled.');

            return self::SUCCESS;
        }

        $stats = $refresher->refreshDue(
            max(1, min(50, (int) $this->option('limit'))),
            max(5, min(45, (int) $this->option('deadline'))),
        );
        $this->info(sprintf(
            'processed=%d updated=%d failed=%d deferred=%d',
            $stats['processed'], $stats['updated'], $stats['failed'], $stats['deferred'],
        ));

        return self::SUCCESS;
    }
}
