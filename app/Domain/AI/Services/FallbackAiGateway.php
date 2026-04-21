<?php

namespace App\Domain\AI\Services;

use App\Contracts\AiGatewayInterface;

/**
 * يجرب المزوّد الأساسي ثم الثانوي عند غياب نص صالح في الاستجابة.
 */
class FallbackAiGateway implements AiGatewayInterface
{
    public function __construct(
        private readonly AiGatewayInterface $primary,
        private readonly AiGatewayInterface $secondary,
    ) {}

    public function requestContent(string $prompt, ?string $systemPrompt = null): ?array
    {
        $first = $this->primary->requestContent($prompt, $systemPrompt);
        if ($this->hasAssistantText($first)) {
            return $first;
        }

        return $this->secondary->requestContent($prompt, $systemPrompt);
    }

    public function generateText(string $prompt, ?string $systemPrompt = null): ?string
    {
        $text = $this->primary->generateText($prompt, $systemPrompt);
        if ($text !== null && trim($text) !== '') {
            return $text;
        }

        return $this->secondary->generateText($prompt, $systemPrompt);
    }

    /**
     * @param  array<string, mixed>|null  $response
     */
    private function hasAssistantText(?array $response): bool
    {
        if ($response === null) {
            return false;
        }

        if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            return true;
        }

        $content = $response['choices'][0]['message']['content'] ?? null;

        return is_string($content) && trim($content) !== '';
    }
}
