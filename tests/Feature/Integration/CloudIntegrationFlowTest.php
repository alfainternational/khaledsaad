<?php

namespace Tests\Feature\Integration;

use App\Application\Integration\CloudIntegrationService;
use App\Contracts\CloudClientContract;
use App\Domain\Account\Models\Account;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Integration\Exceptions\CloudIntegrationException;
use App\Domain\Integration\Services\CloudIntegrationGate;
use App\Domain\Integration\Services\HttpCloudClient;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Database\Seeders\PlatformBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CloudIntegrationFlowTest extends TestCase
{
    use RefreshDatabase;

    private function resetCloudSingletons(): void
    {
        foreach ([
            CloudClientContract::class,
            HttpCloudClient::class,
            CloudIntegrationGate::class,
            CloudIntegrationService::class,
        ] as $abstract) {
            if ($this->app->bound($abstract)) {
                $this->app->forgetInstance($abstract);
            }
        }
    }

    #[Test]
    public function cloud_integration_service_performs_get_when_policy_checks_are_bypassed_for_tests(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);

        config([
            'cloud.enabled' => true,
            'cloud.base_url' => 'https://cloud.test',
            'cloud.policy.enforce_feature_flag' => false,
            'cloud.policy.enforce_entitlement' => false,
        ]);
        $this->resetCloudSingletons();

        Http::fake([
            'https://cloud.test/*' => Http::response(['ping' => 'pong'], 200),
        ]);

        $user = User::factory()->create();
        $plan = Plan::query()->where('code', 'pro')->firstOrFail();
        $account = Account::query()->create([
            'owner_user_id' => $user->id,
            'name' => 'Cloud test account',
            'billing_email' => $user->email,
            'status' => 'active',
        ]);
        Subscription::query()->create([
            'account_id' => $account->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'current_period_end' => now()->addMonth(),
        ]);
        $workspace = Workspace::query()->create([
            'account_id' => $account->id,
            'name' => 'Cloud test workspace',
            'type' => 'personal',
            'status' => 'active',
        ]);

        $service = app(CloudIntegrationService::class);
        $result = $service->get($workspace, $user, '/v1/ping');

        $this->assertSame(['ping' => 'pong'], $result);
        Http::assertSentCount(1);
    }

    #[Test]
    public function cloud_integration_denies_free_plan_when_entitlement_is_enforced(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);

        config([
            'cloud.enabled' => true,
            'cloud.base_url' => 'https://cloud.test',
            'cloud.policy.enforce_feature_flag' => false,
            'cloud.policy.enforce_entitlement' => true,
        ]);
        $this->resetCloudSingletons();

        Http::fake();

        $user = User::factory()->create();
        $plan = Plan::query()->where('code', 'free')->firstOrFail();
        $account = Account::query()->create([
            'owner_user_id' => $user->id,
            'name' => 'Free account',
            'billing_email' => $user->email,
            'status' => 'active',
        ]);
        Subscription::query()->create([
            'account_id' => $account->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'current_period_end' => now()->addMonth(),
        ]);
        $workspace = Workspace::query()->create([
            'account_id' => $account->id,
            'name' => 'Free workspace',
            'type' => 'personal',
            'status' => 'active',
        ]);

        $service = app(CloudIntegrationService::class);

        $this->expectException(CloudIntegrationException::class);
        $service->get($workspace, $user, '/v1/ping');

        Http::assertNothingSent();
    }
}
