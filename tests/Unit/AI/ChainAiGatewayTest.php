<?php

namespace Tests\Unit\AI;

use App\Contracts\AiGatewayInterface;
use App\Domain\AI\Services\ChainAiGateway;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ChainAiGatewayTest extends TestCase
{
    private function gateway(?string $returns): AiGatewayInterface
    {
        return new class($returns) implements AiGatewayInterface
        {
            public int $calls = 0;

            public function __construct(private readonly ?string $returns) {}

            public function requestContent(string $prompt, ?string $systemPrompt = null): ?array
            {
                $this->calls++;

                return $this->returns === null ? null
                    : ['choices' => [['message' => ['content' => $this->returns]]]];
            }

            public function generateText(string $prompt, ?string $systemPrompt = null): ?string
            {
                $this->calls++;

                return $this->returns;
            }
        };
    }

    #[Test]
    public function it_returns_first_successful_and_skips_failed_providers(): void
    {
        $a = $this->gateway(null);        // يفشل
        $b = $this->gateway('نتيجة B');   // ينجح
        $c = $this->gateway('نتيجة C');   // يجب ألّا يُستدعى

        $chain = new ChainAiGateway($a, $b, $c);

        $this->assertSame('نتيجة B', $chain->generateText('س'));
        $this->assertSame(1, $a->calls);
        $this->assertSame(1, $b->calls);
        $this->assertSame(0, $c->calls, 'يتوقّف عند أوّل نجاح');
    }

    #[Test]
    public function it_returns_null_when_all_fail(): void
    {
        $chain = new ChainAiGateway($this->gateway(null), $this->gateway(null));
        $this->assertNull($chain->generateText('س'));
    }

    #[Test]
    public function it_treats_blank_text_as_failure(): void
    {
        $chain = new ChainAiGateway($this->gateway('   '), $this->gateway('صالح'));
        $this->assertSame('صالح', $chain->generateText('س'));
    }
}
