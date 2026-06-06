<?php

namespace Tests\Feature\Agency;

use App\Domain\Account\Models\Account;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Execution\Models\ExecutionPackage;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceMember;
use App\Models\User;
use Database\Seeders\PlatformBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AgencyWhiteLabelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function non_entitled_plan_cannot_open_branding_settings(): void
    {
        [$owner, $workspace] = $this->scenario('pro');

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('agency.branding.edit'))
            ->assertForbidden();
    }

    #[Test]
    public function agency_plan_can_view_and_save_white_label_branding(): void
    {
        [$owner, $workspace] = $this->scenario('agency');

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('agency.branding.edit'))
            ->assertOk()
            ->assertSee('العلامة البيضاء');

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->patch(route('agency.branding.update'), [
                'enabled' => '1',
                'name' => 'وكالة المسار',
                'color' => '#10b981',
                'logo_url' => 'https://example.com/logo.png',
            ])
            ->assertRedirectToRoute('agency.branding.edit');

        $branding = $workspace->fresh()->branding_json;
        $this->assertTrue($branding['enabled']);
        $this->assertSame('وكالة المسار', $branding['name']);
        $this->assertSame('#10b981', $branding['color']);
    }

    #[Test]
    public function client_facing_package_shows_agency_brand_when_white_label_is_on(): void
    {
        [$owner, $workspace] = $this->scenario('agency');
        $workspace->update(['branding_json' => [
            'enabled' => true, 'name' => 'وكالة المسار', 'color' => '#10b981', 'logo_url' => null,
        ]]);

        $package = $this->packageFor($workspace, $owner);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('execution-packages.show', $package))
            ->assertOk()
            ->assertSee('وكالة المسار')
            ->assertDontSee('منصة التسويق الاستراتيجي');
    }

    #[Test]
    public function package_falls_back_to_platform_identity_without_white_label(): void
    {
        [$owner, $workspace] = $this->scenario('pro'); // pro has white_label = false
        $workspace->update(['branding_json' => [
            'enabled' => true, 'name' => 'وكالة المسار', 'color' => '#10b981',
        ]]);

        $package = $this->packageFor($workspace, $owner);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('execution-packages.show', $package))
            ->assertOk()
            ->assertDontSee('وكالة المسار'); // entitlement off → brand not applied
    }

    private function packageFor(Workspace $workspace, User $owner): ExecutionPackage
    {
        $project = \App\Domain\Project\Models\Project::query()->create([
            'public_id' => (string) Str::ulid(),
            'workspace_id' => $workspace->id,
            'name' => 'WL Project',
            'stage' => 1,
            'status' => 'active',
            'sector' => 'general',
        ]);

        return ExecutionPackage::query()->create([
            'public_id' => (string) Str::ulid(),
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'title' => 'حزمة تجريبية',
            'status' => 'proposed',
            'measurement_plan' => 'قياس',
        ]);
    }

    /**
     * @return array{0: User, 1: Workspace}
     */
    private function scenario(string $planCode): array
    {
        $this->seed(PlatformBootstrapSeeder::class);

        $owner = User::factory()->create();
        $plan = Plan::query()->where('code', $planCode)->firstOrFail();

        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'WL Account',
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
            'name' => 'WL Workspace',
            'type' => $planCode === 'agency' ? 'agency' : 'personal',
            'status' => 'active',
        ]);

        WorkspaceMember::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
            'invited_at' => now(),
        ]);

        return [$owner, $workspace];
    }
}
