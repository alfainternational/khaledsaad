<?php

namespace App\Console\Commands;

use App\Support\Settings\SettingsStore;
use Illuminate\Console\Command;

class ToggleWebResearchCommand extends Command
{
    protected $signature = 'web-research:toggle
        {state : on or off}
        {--refresh=keep : on, off, or keep current refresh state}
        {--json : Print machine-readable state}';

    protected $description = 'Persist verified web research and scheduled refresh state';

    public function handle(SettingsStore $settings): int
    {
        $state = strtolower(trim((string) $this->argument('state')));
        $refreshState = strtolower(trim((string) $this->option('refresh')));
        if (! in_array($state, ['on', 'off'], true) || ! in_array($refreshState, ['on', 'off', 'keep'], true)) {
            $this->error('State must be on or off, and refresh must be on, off, or keep.');

            return self::INVALID;
        }

        $enabled = $state === 'on';
        $refreshEnabled = $state === 'off'
            ? false
            : match ($refreshState) {
                'on' => true,
                'off' => false,
                default => (bool) config('services.web_search.scheduled_refresh', false),
            };

        $settings->set('services.web_search.verified_research', $enabled);
        $settings->set('services.web_search.scheduled_refresh', $refreshEnabled);
        config()->set('services.web_search.verified_research', $enabled);
        config()->set('services.web_search.scheduled_refresh', $refreshEnabled);

        if ($this->option('json')) {
            $this->line(json_encode([
                'enabled' => $enabled,
                'refresh_enabled' => $refreshEnabled,
            ], JSON_THROW_ON_ERROR));
        } else {
            $this->info(sprintf(
                'Verified web research is %s; scheduled refresh is %s.',
                $enabled ? 'enabled' : 'disabled',
                $refreshEnabled ? 'enabled' : 'disabled',
            ));
        }

        return self::SUCCESS;
    }
}
