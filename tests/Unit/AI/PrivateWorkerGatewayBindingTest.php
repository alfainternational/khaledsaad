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
}
