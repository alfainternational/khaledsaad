<?php

namespace App\Support\AI;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fires many prompts concurrently against the OpenAI-compatible NVIDIA NIM endpoint
 * (the platform's primary reliable provider) using Laravel's HTTP pool.
 *
 * Used only by the optional sectioned-generation path. Returns null per prompt on any
 * failure so the caller can fall back to the sequential single-call gateway safely.
 */
class ParallelTextGenerator
{
    /**
     * @param  list<string>  $prompts
     * @return list<string|null>  aligned to $prompts (null = failed/empty)
     */
    public function generate(array $prompts, ?string $systemPrompt = null): array
    {
        $count = count($prompts);
        if ($count === 0) {
            return [];
        }

        $key = (string) config('services.nvidia.key', '');
        if ($key === '') {
            return array_fill(0, $count, null);
        }

        $base = rtrim((string) config('services.nvidia.base_url', 'https://integrate.api.nvidia.com/v1'), '/');
        $url = $base.'/chat/completions';
        $model = (string) config('services.nvidia.model', 'meta/llama-3.1-8b-instruct');
        $system = $systemPrompt !== null && trim($systemPrompt) !== ''
            ? $systemPrompt
            : 'أنت مستشار تسويق واستراتيجية محترف تكتب مخرجات تنفيذية دقيقة بالعربية.';
        $temperature = (float) config('services.nvidia.temperature', 0.7);
        // كل قسم قد يكون غنياً؛ نمنح سقفاً أعلى من إعداد النداء الأحادي حتى لا يُبتر قسم.
        $maxTokens = max(3500, (int) config('services.nvidia.max_tokens', 2048));
        $timeout = max(45, (int) config('services.nvidia.timeout', 45));

        try {
            $responses = Http::pool(function (Pool $pool) use ($prompts, $url, $key, $model, $system, $temperature, $maxTokens, $timeout): array {
                $calls = [];
                foreach ($prompts as $index => $prompt) {
                    $calls[] = $pool->as((string) $index)
                        ->withToken($key)
                        ->timeout($timeout)
                        ->acceptJson()
                        ->post($url, [
                            'model' => $model,
                            'messages' => [
                                ['role' => 'system', 'content' => $system],
                                ['role' => 'user', 'content' => $prompt],
                            ],
                            'temperature' => $temperature,
                            'max_tokens' => $maxTokens,
                        ]);
                }

                return $calls;
            });
        } catch (Throwable $e) {
            Log::warning('AI parallel pool failed: '.$e->getMessage());

            return array_fill(0, $count, null);
        }

        $output = [];
        foreach (array_keys($prompts) as $index) {
            $response = $responses[(string) $index] ?? null;
            $text = null;

            if ($response instanceof Response) {
                if ($response->successful()) {
                    $text = $response->json('choices.0.message.content');
                } else {
                    Log::warning('AI parallel section HTTP error', ['status' => $response->status()]);
                }
            } elseif ($response instanceof Throwable) {
                Log::warning('AI parallel section connection error: '.$response->getMessage());
            }

            $output[$index] = is_string($text) && trim($text) !== '' ? trim($text) : null;
        }

        return $output;
    }
}
