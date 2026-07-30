<?php

namespace App\Providers;

use App\Contracts\AI\ArtificialIntelligenceGateway;
use App\Modules\AiReadiness\Contracts\AnswerEngine;
use App\Modules\AiReadiness\GatewayAnswerEngine;
use App\Modules\Intake\Assist\Contracts\AssistEngine;
use App\Modules\Intake\Assist\GatewayAssistEngine;
use App\Support\AI\DeepSeekGateway;
use App\Support\AI\JsonSchemaValidator;
use Illuminate\Support\ServiceProvider;

class ArtificialIntelligenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // المزود يُختار من الإعدادات، فإضافة مزود ثانٍ لاحقًا لا تمس مستدعيًا واحدًا.
        $this->app->bind(ArtificialIntelligenceGateway::class, fn ($app) => match (config('ai.default')) {
            default => $app->make(DeepSeekGateway::class),
        });

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
}
