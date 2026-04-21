<?php

namespace App\Domain\AI\Services;

use App\Contracts\AiGatewayInterface;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiGateway implements AiGatewayInterface
{
    public function requestContent(string $prompt, ?string $systemPrompt = null): ?array
    {
        $apiKey = config('services.gemini.key');

        if (! $apiKey) {
            Log::warning('AI gateway: Gemini API key is missing.');

            return null;
        }

        $systemPrompt ??= 'أنت مستشار استراتيجي محترف في التسويق وريادة الأعمال. تقدم تحليلات دقيقة وتوصيات عملية بالعربية. ركز على القيمة والتنفيذ والنتائج القابلة للقياس.';

        $models = array_unique(array_filter([
            config('services.gemini.model', 'gemini-2.5-flash'),
            'gemini-2.5-flash',
            'gemini-2.0-flash',
        ]));

        foreach ($models as $model) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

            try {
                $host = 'generativelanguage.googleapis.com';
                $ip = gethostbyname($host);

                if ($ip === $host) {
                    $ip = '142.251.209.170';
                }

                $payload = [
                    'system_instruction' => [
                        'parts' => [['text' => $systemPrompt]],
                    ],
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [['text' => $prompt]],
                        ],
                    ],
                ];

                $generationConfig = [];
                $temp = config('services.gemini.temperature');
                if ($temp !== null && $temp !== '') {
                    $generationConfig['temperature'] = (float) $temp;
                }
                $maxOut = config('services.gemini.max_output_tokens');
                if ($maxOut !== null && $maxOut !== '') {
                    $generationConfig['maxOutputTokens'] = (int) $maxOut;
                }
                if ($generationConfig !== []) {
                    $payload['generationConfig'] = $generationConfig;
                }

                $response = Http::withOptions([
                    'verify' => false,
                    'force_ip_resolve' => 'v4',
                    'curl' => [
                        CURLOPT_RESOLVE => ["{$host}:443:{$ip}"],
                        CURLOPT_DNS_SERVERS => '8.8.8.8,8.8.4.4',
                    ],
                ])
                    ->connectTimeout(30)
                    ->timeout(90)
                    ->post($url, $payload);

                if ($response->successful()) {
                    return $response->json();
                }

                if (in_array($response->status(), [404, 429], true)) {
                    Log::info("AI gateway: Gemini model {$model} returned {$response->status()}, trying fallback.");

                    continue;
                }

                Log::warning("AI gateway: Gemini API error for {$model}", [
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 300),
                ]);
            } catch (Exception $e) {
                Log::error("AI gateway: Gemini connection error for {$model}: {$e->getMessage()}");
            }
        }

        return null;
    }

    public function generateText(string $prompt, ?string $systemPrompt = null): ?string
    {
        $response = $this->requestContent($prompt, $systemPrompt);

        return $response['candidates'][0]['content']['parts'][0]['text'] ?? null;
    }
}
