<?php

namespace App\Providers;

use App\Contracts\AI\ArtificialIntelligenceGateway;
use App\Modules\AiReadiness\Contracts\AnswerEngine;
use App\Modules\AiReadiness\GatewayAnswerEngine;
use App\Modules\Intake\Assist\Contracts\AssistEngine;
use App\Modules\Intake\Assist\GatewayAssistEngine;
use App\Support\AI\DeepSeekGateway;
use App\Support\AI\JsonSchemaValidator;
use App\Support\AI\Resilience\CircuitBreaker;
use App\Support\AI\Resilience\FallbackChainGateway;
use App\Support\AI\Resilience\SpendGuard;
use Illuminate\Support\ServiceProvider;

class ArtificialIntelligenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CircuitBreaker::class, fn () => new CircuitBreaker(
            threshold: (int) config('ai.breaker.threshold', 3),
            cooldownSeconds: (int) config('ai.breaker.cooldown_seconds', 300),
        ));

        $this->app->singleton(SpendGuard::class);

        /*
         * البوابة سلسلةٌ لا مزوّدًا واحدًا.
         *
         * كان الربط يعيد `DeepSeekGateway` وحده، فنفادُ اشتراكه كان يوقف
         * المنصة كلها ولا شيء خلفه. السلسلة تجعل سقوط مزوّد تدهورًا في
         * الجودة لا انقطاعًا في الخدمة — وهو الفرق بين مستخدم يعود وآخر
         * يقرأ «تعذّر التشغيل» بعد ستين سؤالًا.
         */
        $this->app->singleton(FallbackChainGateway::class, function ($app) {
            return new FallbackChainGateway($this->chain(), $app->make(CircuitBreaker::class));
        });

        $this->app->bind(
            ArtificialIntelligenceGateway::class,
            fn ($app) => $app->make(FallbackChainGateway::class),
        );

        /*
         * محرك الإجابات فوق البوابة نفسها: مزوّد واحد يُضبط من الإعدادات، فلا
         * يوجد مسار استدعاء ثانٍ لا تُسجَّل تكلفته.
         */
        $this->app->bind(AnswerEngine::class, fn ($app) => $app->make(GatewayAnswerEngine::class));

        /*
         * محرك المساعدة على الأسئلة فوق البوابة نفسها. خلف عقد لسببين: تبديل
         * المزوّد قرار جودة يُتخذ بعد قياس مخرجه العربي، واختبار الواجهة يجب أن
         * يمرّ بلا شبكة — ولو كان الاستدعاء مباشرًا لصار كل اختبار شاشةٍ اختبارًا
         * لمزوّد خارجي.
         */
        $this->app->bind(AssistEngine::class, fn ($app) => $app->make(GatewayAssistEngine::class));

        $this->app->singleton(JsonSchemaValidator::class);
    }

    /**
     * السلسلة كما تُبنى فعليًّا: المعرَّفون في الإعداد ولهم مفتاح.
     *
     * المزوّد بلا مفتاح يُستبعد هنا لا عند الاستدعاء — إبقاؤه يعني أن كل
     * تشغيل يهدر محاولةً على حسابٍ لا وجود له، ويسجّل عطلًا ليس عطلًا.
     *
     * @return array<int, ArtificialIntelligenceGateway>
     */
    private function chain(): array
    {
        $names = (array) config('ai.chain', []);

        if ($names === []) {
            $names = [(string) config('ai.default', 'deepseek')];
        }

        $chain = [];

        foreach ($names as $name) {
            if (config("ai.{$name}.api_key") === null || config("ai.{$name}.api_key") === '') {
                continue;
            }

            $chain[] = new DeepSeekGateway($name);
        }

        // لا يبقى شيء بلا مفاتيح (بيئة اختبار مثلًا): تُعاد البوابة
        // الافتراضية كي يبقى الفشل عطلَ مزوّدٍ مفهومًا لا انهيارَ حاوية.
        return $chain !== []
            ? $chain
            : [new DeepSeekGateway((string) config('ai.default', 'deepseek'))];
    }
}
