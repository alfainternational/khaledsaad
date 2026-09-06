<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\AI\ArtificialIntelligenceGateway;
use App\Exceptions\AIProviderException;
use App\Models\User;
use App\Notifications\ProviderCapacityAlert;
use App\Support\AI\AIRequest;
use App\Support\AI\DeepSeekGateway;
use App\Support\AI\Resilience\CircuitBreaker;
use App\Support\AI\Resilience\FallbackChainGateway;
use App\Support\AI\Resilience\ProviderHealth;
use App\Support\AI\Resilience\SpendGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * فحص استباقي لقدرة مزوّدات الذكاء.
 *
 * **العطل الذي وقع كان يجب أن يصل إلى المشغّل قبل أن يصل إلى مستخدم.**
 * كان أول من يكتشف نفاد الاشتراك هو صاحب الستين إجابة. هذا الأمر يجرّ
 * الاكتشاف إلى ما قبل ذلك: نداء رخيص جدًّا على كل مزوّد، وتنبيه فور
 * سقوطه أو بلوغ الإنفاق عتبته.
 *
 * النداء رخيص عمدًا (رمز واحد): الفحص الذي يكلّف يصير أول ما يُطفأ عند
 * ضيق الميزانية — أي يختفي في اللحظة التي يُحتاج فيها.
 */
class WatchProviderCapacity extends Command
{
    protected $signature = 'ai:watch-capacity {--probe : يرسل نداءً فعليًّا بدل قراءة الحالة المخزّنة}';

    protected $description = 'يفحص صحة مزوّدات الذكاء وسقف الإنفاق، وينبّه قبل أن يشعر المستخدم';

    public function handle(
        FallbackChainGateway $chain,
        CircuitBreaker $breaker,
        SpendGuard $spend,
    ): int {
        $alerts = [];

        foreach ($chain->health() as $provider => $health) {
            if ($this->option('probe') && $health->canServe()) {
                $health = $this->probe($provider, $breaker);
            }

            $this->line("{$provider}: {$health->value}");

            if (! $health->canServe()) {
                $alerts[] = __('المزوّد :provider: :state', [
                    'provider' => $provider,
                    'state' => $health->label(),
                ]);
            }
        }

        // لا مزوّد يخدم: المنصة في وضع محدود الآن، وهذا أشد ما يُنبَّه عليه.
        if (! $chain->hasCapacity()) {
            $alerts[] = __('لا مزوّد قادر على الخدمة — التوليد متوقف والمنصة في وضع محدود.');
        }

        $ratio = $spend->ratio();
        $threshold = (float) config('ai.quota_alert_threshold', 0.2);

        // التنبيه عند اقتراب السقف لا عند بلوغه: بعد البلوغ لم يعد تنبيهًا.
        if ($ratio !== null && $ratio >= (1 - $threshold)) {
            $alerts[] = __('الإنفاق اليومي بلغ :percent من السقف.', [
                'percent' => round($ratio * 100).'٪',
            ]);
        }

        if ($alerts === []) {
            $this->info('المزوّدات تعمل والإنفاق ضمن السقف.');

            return self::SUCCESS;
        }

        foreach ($alerts as $alert) {
            $this->warn($alert);
        }

        $admins = User::where('is_admin', true)->get();

        if ($admins->isNotEmpty()) {
            Notification::send($admins, new ProviderCapacityAlert($alerts));
        }

        return self::FAILURE;
    }

    /**
     * نداء بأصغر حجم ممكن: يكشف نفاد الحصة ومفتاحًا باطلًا وانقطاعًا،
     * ولا يكشف بطء التوليد — وهو ما لا يحتاج فحصًا دوريًّا أصلًا.
     */
    private function probe(string $provider, CircuitBreaker $breaker): ProviderHealth
    {
        try {
            app(ArtificialIntelligenceGateway::class);

            (new DeepSeekGateway($provider))->run(new AIRequest(
                messages: [['role' => 'user', 'content' => 'ping']],
                tier: 'economy',
                maxTokens: 1,
            ));

            $breaker->recordSuccess($provider);

            return ProviderHealth::Ok;
        } catch (AIProviderException $exception) {
            $breaker->recordFailure($provider, $exception);

            return $breaker->health($provider);
        }
    }
}
