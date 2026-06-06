<?php

namespace Tests\Feature\App;

use App\Domain\Account\Models\Account;
use App\Domain\AI\Models\AIGeneration;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceMember;
use App\Models\User;
use Database\Seeders\PlatformBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Enforces the core monetization rule (rebuild plan, Phase 0):
 * registration unlocks reading; subscription unlocks taking value out (export).
 */
class OutputExportEntitlementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function free_plan_user_cannot_export_studio_output(): void
    {
        [$owner, $generation] = $this->scenario('free');

        $this->actingAs($owner)
            ->get(route('studio.generations.export', [$generation, 'md']))
            ->assertForbidden();
    }

    #[Test]
    public function subscribed_user_can_export_studio_output(): void
    {
        [$owner, $generation] = $this->scenario('pro');

        $this->actingAs($owner)
            ->get(route('studio.generations.export', [$generation, 'md']))
            ->assertOk()
            ->assertHeader('content-type', 'text/markdown; charset=UTF-8');
    }

    /**
     * @return array{0: User, 1: AIGeneration}
     */
    private function scenario(string $planCode): array
    {
        $this->seed(PlatformBootstrapSeeder::class);

        $owner = User::factory()->create();
        $plan = Plan::query()->where('code', $planCode)->firstOrFail();

        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Gate Account',
            'billing_email' => $owner->email,
            'status' => 'active',
        ]);

        Subscription::query()->create([
            'account_id' => $account->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $workspace = Workspace::query()->create([
            'account_id' => $account->id,
            'name' => 'Gate Workspace',
            'type' => 'personal',
            'status' => 'active',
        ]);

        WorkspaceMember::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
            'invited_at' => now(),
        ]);

        $generation = AIGeneration::query()->create([
            'account_id' => $account->id,
            'workspace_id' => $workspace->id,
            'project_id' => null,
            'template_id' => null,
            'created_by' => $owner->id,
            'inputs_json' => [],
            'output' => "# عنوان تجريبي\n\nمحتوى للتصدير.",
            'tokens_used' => 0,
            'status' => 'completed',
        ]);

        return [$owner, $generation];
    }
}
