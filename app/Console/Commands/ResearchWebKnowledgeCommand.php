<?php

namespace App\Console\Commands;

use App\Domain\AI\Web\WebResearchService;
use Illuminate\Console\Command;

class ResearchWebKnowledgeCommand extends Command
{
    protected $signature = 'knowledge:research-web {query : Research query} {--depth=3 : Maximum pages fetched}';

    protected $description = 'Run one bounded verified web research pass';

    public function handle(WebResearchService $research): int
    {
        if (! (bool) config('services.web_search.verified_research', false)) {
            $this->warn('Verified web research is disabled.');

            return self::SUCCESS;
        }

        $depth = max(1, min((int) config('services.web_search.max_results', 8), (int) $this->option('depth')));
        $result = $research->research((string) $this->argument('query'), $depth);
        $this->info(sprintf(
            'research_run=%s findings=%d',
            (string) ($result['research_run_id'] ?? 'none'),
            count((array) ($result['findings'] ?? [])),
        ));

        return self::SUCCESS;
    }
}
