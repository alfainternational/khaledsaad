<?php

namespace App\Domain\AI\Services;

use App\Contracts\AiGatewayInterface;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * بوّابة عامة لأي مزوّد متوافق مع OpenAI Chat Completions (Groq/Cerebras/OpenRouter…).
 *
 * كلها «بدائل drop-in» تختلف فقط في base_url + المفتاح + اسم النموذج، فبوّابة
 * واحدة مُعامَلة بملف المزوّد تكفيها جميعاً. تقرأ الإعداد وقت النداء ليلتقط
 * تجاوزات الآدمن (SettingsStore) فوراً. تتدهور بأمان (null) عند غياب المفتاح.
 */
class OpenAiCompatibleGateway implements AiGatewayInterface
{
    /**
     * @param  string|null  $keyOverride  مفتاح خاص بالحساب (BYOK) يتجاوز مفتاح المنصة العام.
     */
    public function __construct(
        private readonly string $provider,
        private readonly ?string $keyOverride = null,
    ) {}

    public function requestContent(string $prompt, ?string $systemPrompt = null): ?array
    {
        $apiKey = $this->keyOverride ?: $this->cfg('key');
        if (! $apiKey) {
            Log::warning("AI gateway: {$this->provider} API key is missing.");

            return null;
        }

        $systemPrompt ??= 'أنت مستشار استراتيجي محترف في التسويق وريادة الأعمال. تقدّم تحليلات دقيقة وتوصيات عملية بالعربية. ركّز على القيمة والتنفيذ والنتائج القابلة للقياس.';

        $base = rtrim((string) $this->cfg('base_url'), '/');
        $url = "{$base}/chat/completions";

        $payload = [
            'model' => (string) $this->cfg('model'),
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => (float) $this->cfg('temperature', 0.5),
            'max_tokens' => (int) $this->cfg('max_tokens', 2048),
        ];

        try {
            $request = Http::withToken((string) $apiKey)
                ->withOptions(['curl' => [
                    CURLOPT_FRESH_CONNECT => true,
                    CURLOPT_FORBID_REUSE => true,
                ]])
                ->connectTimeout((int) $this->cfg('connect_timeout', 15))
                ->timeout((int) $this->cfg('timeout', 45))
                ->acceptJson();

            // OpenRouter يوصي برأسي التعريف (لا يؤثّران على المزوّدات الأخرى).
            if ($this->provider === 'openrouter') {
                $request = $request->withHeaders([
                    'HTTP-Referer' => (string) config('app.url', 'https://khaledsaad.net'),
                    'X-Title' => 'Khaled Saad Marketing Platform',
                ]);
            }

            $response = $request->post($url, $payload);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning("AI gateway: {$this->provider} API error", [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 400),
            ]);
        } catch (Exception $e) {
            Log::error("AI gateway: {$this->provider} connection error: ".$e->getMessage());
        }

        return null;
    }

    public function generateText(string $prompt, ?string $systemPrompt = null): ?string
    {
        $response = $this->requestContent($prompt, $systemPrompt);

        $text = $response['choices'][0]['message']['content'] ?? null;

        return is_string($text) && trim($text) !== '' ? $text : null;
    }

    /**
     * بثّ الرد رمزاً برمز (stream=true). يستدعي [$onDelta] لكل مقطع نصّي جديد.
     * يعيد true عند نجاح البثّ، false عند تعذّره (فيتدهور المتصل لغير المتدفّق).
     *
     * @param  callable(string): void  $onDelta
     */
    public function streamText(string $prompt, ?string $systemPrompt, callable $onDelta): bool
    {
        $apiKey = $this->keyOverride ?: $this->cfg('key');
        if (! $apiKey) {
            return false;
        }

        $systemPrompt ??= 'أنت مستشار استراتيجي محترف في التسويق وريادة الأعمال. تقدّم تحليلات دقيقة وتوصيات عملية بالعربية.';
        $base = rtrim((string) $this->cfg('base_url'), '/');

        $payload = [
            'model' => (string) $this->cfg('model'),
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => (float) $this->cfg('temperature', 0.5),
            'max_tokens' => (int) $this->cfg('max_tokens', 2048),
            'stream' => true,
        ];

        try {
            $request = Http::withToken((string) $apiKey)
                ->withOptions(['stream' => true])
                ->connectTimeout((int) $this->cfg('connect_timeout', 15))
                ->timeout((int) $this->cfg('timeout', 120))
                ->acceptJson();

            if ($this->provider === 'openrouter') {
                $request = $request->withHeaders([
                    'HTTP-Referer' => (string) config('app.url', 'https://khaledsaad.net'),
                    'X-Title' => 'Khaled Saad Marketing Platform',
                ]);
            }

            $response = $request->post("{$base}/chat/completions", $payload);
            if (! $response->successful()) {
                return false;
            }

            $body = $response->toPsrResponse()->getBody();
            $buffer = '';
            $emitted = false;
            while (! $body->eof()) {
                $buffer .= $body->read(2048);
                while (($nl = strpos($buffer, "\n")) !== false) {
                    $line = trim(substr($buffer, 0, $nl));
                    $buffer = substr($buffer, $nl + 1);
                    if ($line === '' || ! str_starts_with($line, 'data:')) {
                        continue;
                    }
                    $data = trim(substr($line, 5));
                    if ($data === '[DONE]') {
                        return $emitted;
                    }
                    $json = json_decode($data, true);
                    $delta = $json['choices'][0]['delta']['content'] ?? null;
                    if (is_string($delta) && $delta !== '') {
                        $onDelta($delta);
                        $emitted = true;
                    }
                }
            }

            return $emitted;
        } catch (Exception $e) {
            Log::warning("AI stream: {$this->provider} error: ".$e->getMessage());

            return false;
        }
    }

    private function cfg(string $key, mixed $default = null): mixed
    {
        return config("services.ai.providers.{$this->provider}.{$key}", $default);
    }
}
