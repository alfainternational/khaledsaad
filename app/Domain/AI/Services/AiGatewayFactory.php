<?php

namespace App\Domain\AI\Services;

use App\Contracts\AiGatewayInterface;
use App\Domain\Account\Models\Account;

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
            'private_worker' => app(PrivateWorkerAiGateway::class),
            default => null,
        };
    }

    /**
     * بوّابة مخصّصة لحساب ربط مفتاحه الخاص (BYOK).
     * تعمل فقط مع المزوّدات المتوافقة مع OpenAI. تعيد null إن لم يربط الحساب مفتاحاً.
     */
    public function makeForAccount(Account $account): ?AiGatewayInterface
    {
        if (! $account->hasByoAi()) {
            return null;
        }

        $provider = (string) $account->ai_provider;
        if (! in_array($provider, self::OPENAI_COMPATIBLE, true)) {
            return null;
        }

        return new OpenAiCompatibleGateway($provider, (string) $account->ai_provider_key);
    }

    /**
     * أول بوّابة متوافقة مع OpenAI قابلة للبثّ (لها مفتاح مضبوط).
     * يُفضَّل مفتاح الحساب (BYOK) إن وُجد، وإلا أول مزوّد في السلسلة له مفتاح.
     * يعيد null إذا لا مزوّد قابل للبثّ متاح (فيتدهور المتصل لغير المتدفّق).
     */
    public function firstStreamable(?Account $account = null): ?OpenAiCompatibleGateway
    {
        if ($account !== null) {
            $byok = $this->makeForAccount($account);
            if ($byok instanceof OpenAiCompatibleGateway) {
                return $byok;
            }
        }

        $chain = array_filter(array_map(
            'trim',
            explode(',', (string) config('services.ai.chain', 'groq,cerebras'))
        ));

        foreach ($chain as $name) {
            if (in_array($name, self::OPENAI_COMPATIBLE, true)
                && filled(config("services.ai.providers.{$name}.key"))) {
                return new OpenAiCompatibleGateway($name);
            }
        }

        // احتياط: أي مزوّد OpenAI-compatible له مفتاح.
        foreach (self::OPENAI_COMPATIBLE as $name) {
            if (filled(config("services.ai.providers.{$name}.key"))) {
                return new OpenAiCompatibleGateway($name);
            }
        }

        return null;
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
