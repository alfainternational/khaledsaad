<?php

namespace App\Domain\AI\Services;

use App\Contracts\AiGatewayInterface;

/**
 * مصنع البوّابات — يترجم اسم مزوّد إلى بوّابة، ويبني سلسلة من قائمة مرتّبة.
 * مصدر واحد لمعرفة كيف يُنشأ كل مزوّد، فيسهل إضافة مزوّد جديد بسطر واحد.
 */
class AiGatewayFactory
{
    /** المزوّدات المتوافقة مع OpenAI (بوّابة عامة واحدة تكفيها). */
    private const OPENAI_COMPATIBLE = ['groq', 'cerebras', 'openrouter'];

    public function make(string $name): ?AiGatewayInterface
    {
        $name = trim($name);

        if (in_array($name, self::OPENAI_COMPATIBLE, true)) {
            return new OpenAiCompatibleGateway($name);
        }

        return match ($name) {
            'gemini' => new GeminiGateway,
            'nvidia' => new NvidiaNimGateway,
            default => null,
        };
    }

    /**
     * @param  array<int, string>  $names
     */
    public function chain(array $names): ChainAiGateway
    {
        $gateways = [];
        foreach ($names as $name) {
            $gateway = $this->make($name);
            if ($gateway !== null) {
                $gateways[] = $gateway;
            }
        }

        // احتياط: سلسلة فارغة تسقط لبوّابة معطّلة بأمان بدل خطأ.
        if ($gateways === []) {
            $gateways[] = new NullAiGateway;
        }

        return new ChainAiGateway(...$gateways);
    }
}
