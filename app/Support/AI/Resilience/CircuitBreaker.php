<?php

declare(strict_types=1);

namespace App\Support\AI\Resilience;

use App\Exceptions\AIProviderException;
use Illuminate\Support\Facades\Cache;

/**
 * قاطع دارة لكل مزوّد.
 *
 * العطل الذي يسدّه: حين ينفد اشتراك المزوّد، كان كل تشغيل جديد يستنزف
 * محاولاته الثلاث ثم يفشل — فيدفع كل مستخدم ثمن الانتظار، ونحن نعلم من
 * أول فشل أن البقية ستفشل. القاطع يفتح بعد عتبة، فتصير الأعطال التالية
 * فوريةً وتُحوَّل إلى الاحتياطي بلا تأخير.
 *
 * الحالة في الكاش لا في قاعدة البيانات: هي حالة تشغيلية عمرها دقائق،
 * وكتابتها في جدول تضيف كتابةً على كل استدعاء بلا فائدة.
 */
final class CircuitBreaker
{
    /**
     * نفاد الحصة ليس عطلًا عابرًا: يفتح القاطع من أول مرة، لأن المحاولة
     * الثانية على حسابٍ فارغ فاشلةٌ بيقين لا باحتمال.
     */
    private const EXHAUSTED_STATUSES = [402, 429];

    public function __construct(
        private readonly int $threshold = 3,
        private readonly int $cooldownSeconds = 300,
    ) {}

    public function isOpen(string $provider): bool
    {
        return ! $this->health($provider)->canServe();
    }

    public function health(string $provider): ProviderHealth
    {
        $state = Cache::get($this->stateKey($provider));

        if ($state !== null) {
            return ProviderHealth::tryFrom((string) $state) ?? ProviderHealth::Ok;
        }

        // أعطال متفرقة دون بلوغ العتبة: يعمل ولا يُوثق به وحده.
        return $this->failures($provider) > 0
            ? ProviderHealth::Degraded
            : ProviderHealth::Ok;
    }

    public function recordSuccess(string $provider): void
    {
        Cache::forget($this->failureKey($provider));
        Cache::forget($this->stateKey($provider));
    }

    public function recordFailure(string $provider, ?\Throwable $exception = null): void
    {
        $status = $exception instanceof AIProviderException ? $exception->statusCode : null;

        if ($status !== null && in_array($status, self::EXHAUSTED_STATUSES, true)) {
            $this->open($provider, ProviderHealth::Exhausted);

            return;
        }

        $failures = (int) Cache::get($this->failureKey($provider), 0) + 1;
        Cache::put($this->failureKey($provider), $failures, $this->cooldownSeconds);

        if ($failures >= $this->threshold) {
            $this->open($provider, ProviderHealth::Down);
        }
    }

    /**
     * فتحٌ يدوي من لوحة الإدارة — لسحب مزوّد من الخدمة بقرار لا بعطل.
     */
    public function open(string $provider, ProviderHealth $reason = ProviderHealth::Down): void
    {
        Cache::put($this->stateKey($provider), $reason->value, $this->cooldownSeconds);
    }

    public function close(string $provider): void
    {
        $this->recordSuccess($provider);
    }

    public function failures(string $provider): int
    {
        return (int) Cache::get($this->failureKey($provider), 0);
    }

    private function failureKey(string $provider): string
    {
        return "ai:breaker:failures:{$provider}";
    }

    private function stateKey(string $provider): string
    {
        return "ai:breaker:state:{$provider}";
    }
}
