<?php

namespace Tests\Feature\AI;

use App\Contracts\AiGatewayInterface;
use App\Domain\AI\Services\CachingAiGateway;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CachingAiGatewayTest extends TestCase
{
    #[Test]
    public function identical_prompts_hit_the_cache_and_skip_the_paid_call(): void
    {
        Cache::flush();
        $inner = new CountingGateway();
        $gateway = new CachingAiGateway($inner, 60);

        $first = $gateway->generateText('حلّل هذا', 'نظام');
        $second = $gateway->generateText('حلّل هذا', 'نظام');

        $this->assertSame('text-1', $first);
        $this->assertSame('text-1', $second); // same cached value
        $this->assertSame(1, $inner->textCalls); // inner called only once

        // A different prompt is a fresh call.
        $gateway->generateText('سؤال آخر', 'نظام');
        $this->assertSame(2, $inner->textCalls);
    }

    #[Test]
    public function failed_calls_are_not_cached(): void
    {
        Cache::flush();
        $inner = new CountingGateway(returnNull: true);
        $gateway = new CachingAiGateway($inner, 60);

        $gateway->requestContent('x');
        $gateway->requestContent('x');

        // Null (failed) results are not cached, so the inner gateway is retried.
        $this->assertSame(2, $inner->contentCalls);
    }
}

class CountingGateway implements AiGatewayInterface
{
    public int $textCalls = 0;
    public int $contentCalls = 0;

    public function __construct(private readonly bool $returnNull = false) {}

    public function requestContent(string $prompt, ?string $systemPrompt = null): ?array
    {
        $this->contentCalls++;

        return $this->returnNull ? null : ['n' => $this->contentCalls];
    }

    public function generateText(string $prompt, ?string $systemPrompt = null): ?string
    {
        $this->textCalls++;

        return $this->returnNull ? null : 'text-'.$this->textCalls;
    }
}
