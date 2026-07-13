<?php

namespace Tests\Unit\AI;

use App\Contracts\AiGatewayInterface;
use App\Domain\AI\Services\PrivateWorkerAiGateway;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PrivateWorkerGatewayBindingTest extends TestCase
{
    #[Test]
    public function private_worker_provider_never_constructs_an_external_gateway(): void
    {
        config()->set('services.ai.provider', 'private_worker');
        config()->set('services.ai.cache', false);
        config()->set('services.private_worker.enabled', true);
        $this->app->forgetInstance(AiGatewayInterface::class);

        $gateway = $this->app->make(AiGatewayInterface::class);

        $this->assertInstanceOf(PrivateWorkerAiGateway::class, $gateway);
    }

    #[Test]
    public function external_generation_lock_forces_the_private_worker_even_if_provider_setting_drifts(): void
    {
        config()->set('services.ai.provider', 'groq');
        config()->set('services.ai.external_generation_disabled', true);
        config()->set('services.ai.cache', false);
        config()->set('services.private_worker.enabled', true);
        $this->app->forgetInstance(AiGatewayInterface::class);

        $this->assertInstanceOf(PrivateWorkerAiGateway::class, $this->app->make(AiGatewayInterface::class));
    }
}
