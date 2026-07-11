<?php

namespace App\Console\Commands;

use App\Domain\AI\Worker\Models\IntelligenceWorker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class ProvisionPrivateWorkerCommand extends Command
{
    protected $signature = 'private-worker:provision
        {name : Human-readable worker name}
        {--capability=* : Worker capability}
        {--json : Print the one-time credentials as JSON}';

    protected $description = 'Provision a private intelligence worker and print its secret once';

    private const ALLOWED = [
        'ocr',
        'document_extract',
        'embeddings',
        'rerank',
        'local_llm',
        'deterministic_echo',
    ];

    public function handle(): int
    {
        $name = trim((string) $this->argument('name'));
        $capabilities = array_values(array_unique(array_map('strval', $this->option('capability'))));
        if ($name === '' || mb_strlen($name) > 120) {
            $this->error('Worker name must contain between 1 and 120 characters.');

            return self::INVALID;
        }
        if ($capabilities === [] || array_diff($capabilities, self::ALLOWED) !== []) {
            $this->error('At least one valid worker capability is required.');

            return self::INVALID;
        }

        $secret = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        $worker = IntelligenceWorker::query()->create([
            'public_id' => 'wrk_'.Str::lower((string) Str::ulid()),
            'name' => $name,
            'secret_ciphertext' => Crypt::encryptString($secret),
            'capabilities_json' => $capabilities,
            'status' => 'active',
        ]);
        $credentials = [
            'worker_id' => $worker->public_id,
            'worker_secret' => $secret,
            'capabilities' => $capabilities,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($credentials, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } else {
            $this->warn('Store this secret now. It will not be shown again.');
            $this->line('worker_id='.$worker->public_id);
            $this->line('worker_secret='.$secret);
            $this->line('capabilities='.implode(',', $capabilities));
        }

        return self::SUCCESS;
    }
}
