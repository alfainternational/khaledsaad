<?php

namespace App\Providers;

use App\Contracts\AI\ArtificialIntelligenceGateway;
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

        $this->app->singleton(JsonSchemaValidator::class);
    }
}
