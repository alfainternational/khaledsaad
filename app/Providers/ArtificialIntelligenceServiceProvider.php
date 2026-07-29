<?php

namespace App\Providers;

use App\Contracts\AI\ArtificialIntelligenceGateway;
use App\Modules\AiReadiness\Contracts\AnswerEngine;
use App\Modules\AiReadiness\GatewayAnswerEngine;
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

        $this->app->singleton(JsonSchemaValidator::class);
    }
}
