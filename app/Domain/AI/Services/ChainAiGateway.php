<?php

namespace App\Domain\AI\Services;

use App\Contracts\AiGatewayInterface;

/**
 * سلسلة مزوّدات مرتّبة: يجرّب كلاً بالترتيب حتى يعيد أحدهم نصاً صالحاً.
 *
 * تعميم FallbackAiGateway من مزوّدين إلى N — للمرونة والصمود: حين يسقط/يتباطأ
 * مزوّد (حصة، مهلة، مفتاح ناقص) ينتقل تلقائياً للتالي، فتبقى الخدمة قائمة.
 */
class ChainAiGateway implements AiGatewayInterface
{
    /** @var array<int, AiGatewayInterface> */
    private array $gateways;

    public function __construct(AiGatewayInterface ...$gateways)
    {
        $this->gateways = $gateways;
    }

    public function requestContent(string $prompt, ?string $systemPrompt = null): ?array
    {
        foreach ($this->gateways as $gateway) {
            $result = $gateway->requestContent($prompt, $systemPrompt);
            if ($this->hasText($result)) {
                return $result;
            }
        }

        return null;
    }

    public function generateText(string $prompt, ?string $systemPrompt = null): ?string
    {
        foreach ($this->gateways as $gateway) {
            $text = $gateway->generateText($prompt, $systemPrompt);
            if ($text !== null && trim($text) !== '') {
                return $text;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $response
     */
    private function hasText(?array $response): bool
    {
        if ($response === null) {
            return false;
        }

        // صيغة OpenAI-compatible أو Gemini.
        $openai = $response['choices'][0]['message']['content'] ?? null;
        $gemini = $response['candidates'][0]['content']['parts'][0]['text'] ?? null;

        return (is_string($openai) && trim($openai) !== '') || (is_string($gemini) && trim($gemini) !== '');
    }
}
