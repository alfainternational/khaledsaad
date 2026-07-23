<?php

namespace App\Support\AI;

use App\Exceptions\AIInvalidOutputException;

/**
 * نتيجة استدعاء واحد، بما يكفي لتغذية جدول ai_usage_records مباشرة.
 */
final class AIResponse
{
    public function __construct(
        public readonly string $content,
        public readonly string $provider,
        public readonly string $model,
        public readonly int $inputTokens,
        public readonly int $outputTokens,
        public readonly int $latencyMs,
        public readonly float $costUsd,
        public readonly ?string $stage = null,
        public readonly ?string $finishReason = null,
    ) {}

    public function totalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }

    /**
     * فك المخرج كـJSON. يرمي استثناءً بدل إرجاع null حتى لا يمر مخرج تالف
     * إلى بقية خط الأنابيب بصمت.
     *
     * @return array<string, mixed>
     */
    public function decoded(): array
    {
        $payload = json_decode($this->trimmedJson(), true);

        if (! is_array($payload)) {
            throw new AIInvalidOutputException('تعذر فك مخرج الذكاء الاصطناعي كـJSON صالح.');
        }

        return $payload;
    }

    /**
     * بعض النماذج تغلف JSON داخل سياج ```json رغم طلب وضع JSON.
     */
    private function trimmedJson(): string
    {
        $content = trim($this->content);

        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```[a-zA-Z]*\s*|\s*```$/', '', $content) ?? $content;
        }

        return trim($content);
    }

    /**
     * @return array<string, mixed>
     */
    public function usageRecord(): array
    {
        return [
            'provider' => $this->provider,
            'model' => $this->model,
            'stage' => $this->stage,
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'latency_ms' => $this->latencyMs,
            'cost_usd' => $this->costUsd,
        ];
    }
}
