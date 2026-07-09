<?php

namespace App\Domain\AI\Services;

use App\Contracts\AiGatewayInterface;

/**
 * بوابة معطّلة — تُستخدم عند تفعيل Kill Switch للذكاء من لوحة الآدمن.
 * تُرجع null دائماً فتتدهور كل الطبقات بأمان للمحرك المحلي بلا أي نداء خارجي.
 */
class NullAiGateway implements AiGatewayInterface
{
    public function requestContent(string $prompt, ?string $systemPrompt = null): ?array
    {
        return null;
    }

    public function generateText(string $prompt, ?string $systemPrompt = null): ?string
    {
        return null;
    }
}
