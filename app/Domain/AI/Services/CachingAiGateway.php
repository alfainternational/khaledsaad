<?php

namespace App\Domain\AI\Services;

use App\Contracts\AiGatewayInterface;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Caching decorator over any AI gateway (Phase هـ — engine deepening).
 * Identical prompts (same system + user content) return a cached result instead of
 * a fresh paid API call, cutting current AI spend and smoothing the path to the
 * future self-hosted model. Only successful (non-null) results are cached.
 */
class CachingAiGateway implements AiGatewayInterface
{
    public function __construct(
        private readonly AiGatewayInterface $inner,
        private readonly int $ttlMinutes = 1440,
    ) {}

    public function requestContent(string $prompt, ?string $systemPrompt = null): ?array
    {
        return $this->remember('content', $prompt, $systemPrompt, fn (): ?array => $this->inner->requestContent($prompt, $systemPrompt));
    }

    public function generateText(string $prompt, ?string $systemPrompt = null): ?string
    {
        return $this->remember('text', $prompt, $systemPrompt, fn (): ?string => $this->inner->generateText($prompt, $systemPrompt));
    }

    private function remember(string $kind, string $prompt, ?string $systemPrompt, Closure $callback): mixed
    {
        $key = 'ai:'.$kind.':'.hash('sha256', ($systemPrompt ?? '').'|'.$prompt);

        if (Cache::has($key)) {
            return Cache::get($key);
        }

        $result = $callback();

        if ($result !== null) {
            Cache::put($key, $result, now()->addMinutes($this->ttlMinutes));
        }

        return $result;
    }
}
