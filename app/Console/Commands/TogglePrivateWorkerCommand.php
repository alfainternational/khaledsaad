<?php

namespace App\Console\Commands;

use App\Support\Settings\SettingsStore;
use Illuminate\Console\Command;

class TogglePrivateWorkerCommand extends Command
{
    protected $signature = 'private-worker:toggle
        {state : on or off}
        {--exclusive : Use the private worker as the only generation provider}
        {--wait=90 : Synchronous local generation wait in seconds}
        {--json : Print machine-readable state}';

    protected $description = 'Persist the private worker feature state for both PHP CLI and web processes';

    public function handle(SettingsStore $settings): int
    {
        $state = strtolower(trim((string) $this->argument('state')));
        if (! in_array($state, ['on', 'off'], true)) {
            $this->error('State must be on or off.');

            return self::INVALID;
        }

        $enabled = $state === 'on';
        $exclusive = (bool) $this->option('exclusive');
        $wait = (int) $this->option('wait');
        if ($exclusive && (! $enabled || $wait < 10 || $wait > 180)) {
            $this->error('Exclusive mode requires state=on and a wait between 10 and 180 seconds.');

            return self::INVALID;
        }
        $values = ['services.private_worker.enabled' => $enabled];
        if ($exclusive) {
            $values += [
                'services.ai.provider' => 'private_worker',
                'services.private_worker.prefer_for_generation' => true,
                'services.private_worker.gateway_wait_seconds' => $wait,
            ];
        }
        $settings->setMany($values);
        foreach ($values as $key => $value) {
            config()->set($key, $value);
        }

        if ($this->option('json')) {
            $payload = ['enabled' => $enabled];
            if ($exclusive) {
                $payload += ['provider' => 'private_worker', 'exclusive' => true, 'wait_seconds' => $wait];
            }
            $this->line(json_encode($payload, JSON_THROW_ON_ERROR));
        } else {
            $this->info('Private worker is '.($enabled ? 'enabled' : 'disabled').'.');
        }

        return self::SUCCESS;
    }
}
