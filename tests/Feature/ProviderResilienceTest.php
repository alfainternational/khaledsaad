<?php

namespace Tests\Feature;

use App\Contracts\AI\ArtificialIntelligenceGateway;
use App\Exceptions\AIProviderException;
use App\Support\AI\AIRequest;
use App\Support\AI\AIResponse;
use App\Support\AI\Resilience\CircuitBreaker;
use App\Support\AI\Resilience\FallbackChainGateway;
use App\Support\AI\Resilience\ProviderHealth;
use App\Support\AI\Resilience\SpendGuard;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * سلسلة المزوّدات وقاطع الدارة.
 *
 * ما تحرسه: أن نفاد مزوّدٍ واحد لا يوقف المنصة. هذا بالضبط ما وقع —
 * مزوّد وحيد نفد اشتراكه، فتوقف كل تشغيل، ولم يكن خلفه أحد.
 */
class ProviderResilienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    #[Test]
    public function a_failing_primary_falls_through_to_the_next_provider(): void
    {
        $chain = new FallbackChainGateway(
            [$this->gateway('primary', fails: true), $this->gateway('backup')],
            new CircuitBreaker,
        );

        $response = $chain->run($this->request());

        $this->assertSame('backup', $response->provider, 'لم تنتقل السلسلة إلى الاحتياطي.');
    }

    #[Test]
    public function running_out_of_quota_opens_the_circuit_immediately(): void
    {
        $breaker = new CircuitBreaker(threshold: 3);
        $breaker->recordFailure('primary', new AIProviderException('primary', 402));

        $this->assertTrue($breaker->isOpen('primary'), 'نفاد الحصة يجب ألا ينتظر ثلاث محاولات.');
        $this->assertSame(ProviderHealth::Exhausted, $breaker->health('primary'));
    }

    #[Test]
    public function transient_failures_open_the_circuit_only_at_the_threshold(): void
    {
        $breaker = new CircuitBreaker(threshold: 3);

        $breaker->recordFailure('primary', new AIProviderException('primary', 500));
        $this->assertFalse($breaker->isOpen('primary'));
        $this->assertSame(ProviderHealth::Degraded, $breaker->health('primary'));

        $breaker->recordFailure('primary', new AIProviderException('primary', 500));
        $breaker->recordFailure('primary', new AIProviderException('primary', 500));
        $this->assertTrue($breaker->isOpen('primary'));
    }

    /**
     * القاطع المفتوح يوفّر المحاولة كلها، لا يجعلها تفشل أسرع.
     */
    #[Test]
    public function an_open_circuit_is_skipped_without_being_called(): void
    {
        $calls = 0;
        $breaker = new CircuitBreaker;
        $breaker->open('primary');

        $chain = new FallbackChainGateway([
            $this->gateway('primary', fails: true, counter: $calls),
            $this->gateway('backup'),
        ], $breaker);

        $chain->run($this->request());

        $this->assertSame(0, $calls, 'نُودي مزوّدٌ قاطعه مفتوح.');
    }

    #[Test]
    public function a_success_closes_the_circuit_again(): void
    {
        $breaker = new CircuitBreaker(threshold: 1);
        $breaker->recordFailure('primary', new AIProviderException('primary', 500));
        $this->assertTrue($breaker->isOpen('primary'));

        $breaker->recordSuccess('primary');
        $this->assertFalse($breaker->isOpen('primary'));
    }

    /**
     * حين يسقط الجميع، العطل يبقى عطلًا — ولا يُخترع مخرج.
     */
    #[Test]
    public function an_exhausted_chain_reports_a_provider_failure_not_a_silent_success(): void
    {
        $chain = new FallbackChainGateway(
            [$this->gateway('a', fails: true), $this->gateway('b', fails: true)],
            new CircuitBreaker,
        );

        $this->expectException(AIProviderException::class);
        $chain->run($this->request());
    }

    #[Test]
    public function the_chain_reports_when_no_provider_can_serve(): void
    {
        $breaker = new CircuitBreaker;
        $chain = new FallbackChainGateway([$this->gateway('a'), $this->gateway('b')], $breaker);

        $this->assertTrue($chain->hasCapacity());

        $breaker->open('a');
        $breaker->open('b');

        $this->assertFalse($chain->hasCapacity(), 'الوضع المحدود لم يُرصد.');
    }

    /**
     * سقفٌ صفر يعني «بلا سقف» لا «لا تنفق» — والخلط بينهما يوقف المنصة.
     */
    #[Test]
    public function a_zero_spend_cap_means_unlimited_not_blocked(): void
    {
        config(['ai.daily_spend_cap_usd' => 0]);

        $this->assertTrue(app(SpendGuard::class)->hasCapacity());
        $this->assertNull(app(SpendGuard::class)->ratio());
    }

    private function request(): AIRequest
    {
        return new AIRequest(messages: [['role' => 'user', 'content' => 'مرحبًا']]);
    }

    private function gateway(string $name, bool $fails = false, ?int &$counter = null): ArtificialIntelligenceGateway
    {
        return new class($name, $fails, $counter) implements ArtificialIntelligenceGateway
        {
            public function __construct(
                private string $name,
                private bool $fails,
                private ?int &$counter,
            ) {}

            public function provider(): string
            {
                return $this->name;
            }

            public function modelForTier(string $tier): string
            {
                return $this->name.'-model';
            }

            public function run(AIRequest $request): AIResponse
            {
                if ($this->counter !== null) {
                    $this->counter++;
                }

                if ($this->fails) {
                    throw new AIProviderException($this->name, 500);
                }

                return new AIResponse(
                    content: 'نتيجة',
                    provider: $this->name,
                    model: $this->modelForTier($request->tier),
                    inputTokens: 1,
                    outputTokens: 1,
                    latencyMs: 1,
                    costUsd: 0.0,
                );
            }

            public function stream(AIRequest $request, Closure $onChunk): AIResponse
            {
                return $this->run($request);
            }
        };
    }
}
