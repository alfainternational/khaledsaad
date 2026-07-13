<?php

namespace App\Console\Commands;

use App\Domain\AI\Worker\Models\IntelligenceWorker;
use Illuminate\Console\Command;

class UpdatePrivateWorkerCapabilitiesCommand extends Command
{
    private const ALLOWED = [
        'ocr', 'document_extract', 'embeddings', 'rerank', 'local_llm', 'deterministic_echo',
    ];

    protected $signature = 'private-worker:update-capabilities
        {worker : Worker public ID}
        {--capability=* : Complete replacement capability list}
        {--json : Print machine-readable output}';

    protected $description = 'Replace the allowed capabilities of an existing private worker without rotating its secret';

    public function handle(): int
    {
        $worker = IntelligenceWorker::query()->where('public_id', (string) $this->argument('worker'))->first();
        $capabilities = array_values(array_unique(array_map('strval', $this->option('capability'))));
        if (! $worker) {
            return $this->result(['ok' => false, 'error' => 'worker_not_found'], self::FAILURE);
        }
        if ($capabilities === [] || array_diff($capabilities, self::ALLOWED) !== []) {
            return $this->result(['ok' => false, 'error' => 'capabilities_invalid'], self::INVALID);
        }

        $worker->update(['capabilities_json' => $capabilities]);

        return $this->result([
            'ok' => true,
            'worker_id' => $worker->public_id,
            'capabilities' => $worker->fresh()->capabilities_json,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function result(array $payload, int $code = self::SUCCESS): int
    {
        $encoded = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $this->line($this->option('json') ? $encoded : json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $code;
    }
}
