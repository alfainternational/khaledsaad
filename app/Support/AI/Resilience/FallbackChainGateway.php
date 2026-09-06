<?php

declare(strict_types=1);

namespace App\Support\AI\Resilience;

use App\Contracts\AI\ArtificialIntelligenceGateway;
use App\Exceptions\AIProviderException;
use App\Support\AI\AIRequest;
use App\Support\AI\AIResponse;
use Closure;
use Illuminate\Support\Facades\Log;

/**
 * سلسلة مزوّدات: يُجرَّب الأول، فإن سقط جُرِّب الذي يليه.
 *
 * القرار المنتَجي خلفها: **تدهور الجودة أفضل من التوقف.** مستخدمٌ ينال
 * تقريرًا من نموذج أضعف خيرٌ من مستخدمٍ يقرأ «تعذّر التشغيل» بعد ستين
 * سؤالًا. والفرق ليس تقنيًّا: الأول يعود، والثاني لا.
 *
 * ويُسجَّل المزوّد الفعلي في `ai_usage_records` دائمًا، فلا يُقارَن هامش
 * تقريرٍ وُلد من الاحتياطي بتكلفة الأساسي.
 */
final class FallbackChainGateway implements ArtificialIntelligenceGateway
{
    /**
     * @param  array<int, ArtificialIntelligenceGateway>  $chain
     */
    public function __construct(
        private readonly array $chain,
        private readonly CircuitBreaker $breaker,
    ) {
        if ($chain === []) {
            throw new \LogicException('سلسلة المزوّدات فارغة: لا مزوّد يخدم الطلب.');
        }
    }

    public function run(AIRequest $request): AIResponse
    {
        return $this->attempt(fn (ArtificialIntelligenceGateway $gateway) => $gateway->run($request));
    }

    /**
     * التدفّق يسقط إلى الاحتياطي **قبل أول جزء فقط**.
     *
     * بعد أن يبدأ النص بالوصول إلى الشاشة، تبديلُ المزوّد يعني نصًّا يكمل
     * نصًّا آخر بأسلوب مختلف وربما بمعلومة مناقِضة. الانقطاع بعد البدء
     * يُعامَل عطلًا صريحًا لا يُرقَّع.
     */
    public function stream(AIRequest $request, Closure $onChunk): AIResponse
    {
        return $this->attempt(function (ArtificialIntelligenceGateway $gateway) use ($request, $onChunk) {
            $started = false;

            try {
                return $gateway->stream($request, function (string $chunk) use ($onChunk, &$started): void {
                    $started = true;
                    $onChunk($chunk);
                });
            } catch (AIProviderException $exception) {
                if ($started) {
                    // وصل نصٌّ للمستخدم: لا يُستأنف من مزوّد آخر.
                    throw new StreamAlreadyStartedException($exception);
                }

                throw $exception;
            }
        });
    }

    public function provider(): string
    {
        return $this->healthy()?->provider() ?? $this->chain[0]->provider();
    }

    public function modelForTier(string $tier): string
    {
        return ($this->healthy() ?? $this->chain[0])->modelForTier($tier);
    }

    /**
     * صحة كل مزوّد في السلسلة — تقرأها اللوحة والبوابة.
     *
     * @return array<string, ProviderHealth>
     */
    public function health(): array
    {
        $health = [];

        foreach ($this->chain as $gateway) {
            $health[$gateway->provider()] = $this->breaker->health($gateway->provider());
        }

        return $health;
    }

    /**
     * هل بقي في السلسلة من يخدم؟ إجابةُ «لا» تعني الوضع المحدود.
     */
    public function hasCapacity(): bool
    {
        return $this->healthy() !== null;
    }

    /**
     * @param  Closure(ArtificialIntelligenceGateway): AIResponse  $call
     */
    private function attempt(Closure $call): AIResponse
    {
        $last = null;
        $skipped = [];

        foreach ($this->chain as $gateway) {
            $name = $gateway->provider();

            if ($this->breaker->isOpen($name)) {
                $skipped[] = $name;

                continue;
            }

            try {
                $response = $call($gateway);
                $this->breaker->recordSuccess($name);

                return $response;
            } catch (StreamAlreadyStartedException $exception) {
                // عطلٌ بعد بدء العرض: يُسجَّل ويُرمى، ولا يُجرَّب بديل.
                $this->breaker->recordFailure($name, $exception->getPrevious());

                throw $exception->getPrevious();
            } catch (AIProviderException $exception) {
                $this->breaker->recordFailure($name, $exception);
                $last = $exception;

                Log::warning('سقط مزوّد الذكاء، ننتقل إلى التالي في السلسلة', [
                    'provider' => $name,
                    'status' => $exception->statusCode,
                ]);
            }
        }

        Log::error('سقطت سلسلة مزوّدات الذكاء كاملةً', [
            'skipped_open' => $skipped,
            'chain' => array_map(fn ($g) => $g->provider(), $this->chain),
        ]);

        // لا مزوّد خدم: الرسالة التي تصل المستخدم يصوغها `FailureClassifier`،
        // وهي عطلٌ لنا لا حدٌّ له.
        throw $last ?? new AIProviderException($this->chain[0]->provider(), null);
    }

    private function healthy(): ?ArtificialIntelligenceGateway
    {
        foreach ($this->chain as $gateway) {
            if (! $this->breaker->isOpen($gateway->provider())) {
                return $gateway;
            }
        }

        return null;
    }
}
