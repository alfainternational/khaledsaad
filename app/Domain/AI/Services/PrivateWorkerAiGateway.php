<?php

namespace App\Domain\AI\Services;

use App\Contracts\AiGatewayInterface;
use App\Domain\AI\Worker\Models\IntelligenceJob;
use App\Domain\AI\Worker\Models\IntelligenceWorker;
use Closure;
use Illuminate\Support\Str;

class PrivateWorkerAiGateway implements AiGatewayInterface
{
    private readonly ?Closure $afterDispatch;

    public function __construct(?callable $afterDispatch = null)
    {
        $this->afterDispatch = $afterDispatch === null ? null : Closure::fromCallable($afterDispatch);
    }

    public function requestContent(string $prompt, ?string $systemPrompt = null): ?array
    {
        if (! (bool) config('services.private_worker.enabled', false) || ! $this->hasOnlineWorker()) {
            return null;
        }

        $payload = [
            'prompt' => $prompt,
            'system_prompt' => $systemPrompt,
            'response_format' => 'json',
        ];
        $job = IntelligenceJob::query()->create([
            'public_id' => (string) Str::uuid(),
            'type' => 'local_llm',
            'status' => 'queued',
            'payload_json' => $payload,
            'input_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)),
            'available_at' => now(),
            'timeout_seconds' => min(600, max(30, (int) config('services.private_worker.gateway_wait_seconds', 8) + 30)),
            'max_attempts' => 1,
        ]);

        if ($this->afterDispatch) {
            ($this->afterDispatch)($job);
        }

        $deadline = microtime(true) + max(1, (int) config('services.private_worker.gateway_wait_seconds', 8));
        do {
            $job->refresh();
            if ($job->status === 'completed' && is_array($job->result_json)) {
                return [
                    'choices' => [[
                        'message' => [
                            'role' => 'assistant',
                            'content' => json_encode(
                                $job->result_json,
                                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                            ),
                        ],
                    ]],
                    'model' => $job->model_name ?? 'private-worker',
                ];
            }
            if (in_array($job->status, ['failed', 'cancelled'], true)) {
                return null;
            }
            usleep(100_000);
        } while (microtime(true) < $deadline);

        IntelligenceJob::query()
            ->whereKey($job->id)
            ->whereIn('status', ['queued', 'leased'])
            ->update([
                'status' => 'cancelled',
                'leased_until' => null,
                'last_error' => 'PRIVATE_WORKER_GATEWAY_TIMEOUT: local generation did not finish in the synchronous wait window.',
            ]);

        return null;
    }

    public function generateText(string $prompt, ?string $systemPrompt = null): ?string
    {
        $response = $this->requestContent($prompt, $systemPrompt);
        $content = $response['choices'][0]['message']['content'] ?? null;

        return is_string($content) && trim($content) !== '' ? $content : null;
    }

    private function hasOnlineWorker(): bool
    {
        return IntelligenceWorker::query()
            ->where('status', 'active')
            ->where('last_seen_at', '>=', now()->subMinutes(5))
            ->whereJsonContains('capabilities_json', 'local_llm')
            ->exists();
    }
}
