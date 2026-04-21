<?php

namespace App\Domain\AI\Services;

use App\Contracts\AiGatewayInterface;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * NVIDIA NIM — OpenAI-compatible chat completions (Bearer nvapi key).
 *
 * @see https://docs.api.nvidia.com/nim/reference/llm-apis
 */
class NvidiaNimGateway implements AiGatewayInterface
{
    public function requestContent(string $prompt, ?string $systemPrompt = null): ?array
    {
        $apiKey = config('services.nvidia.key');

        if (! $apiKey) {
            Log::warning('AI gateway: NVIDIA API key is missing.');

            return null;
        }

        $systemPrompt ??= 'أنت مستشار استراتيجي محترف في التسويق وريادة الأعمال. تقدم تحليلات دقيقة وتوصيات عملية بالعربية. ركز على القيمة والتنفيذ والنتائج القابلة للقياس.';

        $base = rtrim(config('services.nvidia.base_url', 'https://integrate.api.nvidia.com/v1'), '/');
        $url = "{$base}/chat/completions";
        $model = config('services.nvidia.model', 'meta/llama-3.1-8b-instruct');

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => (float) config('services.nvidia.temperature', 0.7),
            'max_tokens' => (int) config('services.nvidia.max_tokens', 8192),
        ];

        try {
            $response = Http::withToken($apiKey)
                ->connectTimeout(30)
                ->timeout(90)
                ->acceptJson()
                ->post($url, $payload);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('AI gateway: NVIDIA NIM API error', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 500),
            ]);
        } catch (Exception $e) {
            Log::error('AI gateway: NVIDIA NIM connection error: '.$e->getMessage());
        }

        return null;
    }

    public function generateText(string $prompt, ?string $systemPrompt = null): ?string
    {
        $response = $this->requestContent($prompt, $systemPrompt);

        $text = $response['choices'][0]['message']['content'] ?? null;

        return is_string($text) && $text !== '' ? $text : null;
    }
}
