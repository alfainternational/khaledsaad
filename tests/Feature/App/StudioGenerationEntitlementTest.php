<?php

namespace Tests\Feature\App;

use App\Domain\Account\Models\Account;
use App\Domain\AI\Models\AITemplate;
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
 * Phase 0: the AI Studio is a paid module. Hiding the page is not enough — the
 * generate endpoint must reject non-entitled plans server-side (direct POST leak).
 */
class StudioGenerationEntitlementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function free_plan_user_cannot_generate_studio_output_via_direct_post(): void
    {
        [$owner, $template] = $this->scenario('free');

        $this->actingAs($owner)
            ->post(route('studio.generations.store'), [
                'template_id' => $template->id,
                'project_id' => null,
                'brief' => 'مسودة سريعة',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function starter_plan_user_is_also_blocked_from_studio_generation(): void
    {
        [$owner, $template] = $this->scenario('starter');

        $this->actingAs($owner)
            ->post(route('studio.generations.store'), [
                'template_id' => $template->id,
                'project_id' => null,
                'brief' => 'مسودة سريعة',
            ])
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: AITemplate}
     */
    private function scenario(string $planCode): array
    {
        $this->seed(PlatformBootstrapSeeder::class);

        $owner = User::factory()->create();
        $plan = Plan::query()->where('code', $planCode)->firstOrFail();

        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Studio Account',
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
            'name' => 'Studio Workspace',
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

        $template = AITemplate::query()->firstOrFail();

        return [$owner, $template];
    }
}
