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
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase ب: subscription unlocks taking value out. Non-subscribers may READ an output
 * inside the platform, but it is watermarked, non-selectable, and shows a read-only
 * banner instead of export buttons. Subscribers see the full export toolbar.
 */
class OutputWatermarkGateTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function non_subscriber_sees_read_only_watermark_and_no_export_buttons(): void
    {
        [$owner, $generation] = $this->scenario('free');

        $this->actingAs($owner)
            ->get(route('studio.generations.show', $generation))
            ->assertOk()
            ->assertSee('نسخة للقراءة فقط')
            ->assertSee('gate-locked', false)
            ->assertSee('gate-watermark', false)
            ->assertSee('رقِّ اشتراكك للنسخ والتصدير')
            ->assertDontSee('تنزيل Markdown');
    }

    #[Test]
    public function subscriber_sees_export_toolbar_without_watermark(): void
    {
        [$owner, $generation] = $this->scenario('pro');

        $this->actingAs($owner)
            ->get(route('studio.generations.show', $generation))
            ->assertOk()
            ->assertSee('تنزيل Markdown')
            ->assertDontSee('نسخة للقراءة فقط')
            ->assertDontSee('gate-watermark', false);
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
            'public_id' => (string) Str::ulid(),
            'account_id' => $account->id,
            'workspace_id' => $workspace->id,
            'project_id' => null,
            'template_id' => null,
            'created_by' => $owner->id,
            'inputs_json' => [],
            'output' => "# القسم الأول\n\nمحتوى المخرج التجريبي للقراءة.",
            'tokens_used' => 0,
            'status' => 'completed',
        ]);

        return [$owner, $generation];
    }
}
