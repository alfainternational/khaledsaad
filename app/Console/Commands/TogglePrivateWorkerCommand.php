<?php

namespace App\Console\Commands;

use App\Support\Settings\SettingsStore;
use Illuminate\Console\Command;

class TogglePrivateWorkerCommand extends Command
{
    protected $signature = 'private-worker:toggle {state : on or off} {--json : Print machine-readable state}';

    protected $description = 'Persist the private worker feature state for both PHP CLI and web processes';

    public function handle(SettingsStore $settings): int
    {
        $state = strtolower(trim((string) $this->argument('state')));
        if (! in_array($state, ['on', 'off'], true)) {
            $this->error('State must be on or off.');

            return self::INVALID;
        }

        $enabled = $state === 'on';
        $settings->set('services.private_worker.enabled', $enabled);
        config()->set('services.private_worker.enabled', $enabled);

        if ($this->option('json')) {
            $this->line(json_encode(['enabled' => $enabled], JSON_THROW_ON_ERROR));
        } else {
            $this->info('Private worker is '.($enabled ? 'enabled' : 'disabled').'.');
        }

        return self::SUCCESS;
    }
}
