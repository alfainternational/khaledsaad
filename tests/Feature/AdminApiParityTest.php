<?php

namespace Tests\Feature;

use App\Models\CreditPack;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Tool;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminApiParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    #[Test]
    public function administrator_api_requires_authentication_and_the_admin_role(): void
    {
        $this->getJson(route('api.v1.admin.dashboard'))->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create(['is_admin' => false]));
        $this->getJson(route('api.v1.admin.dashboard'))->assertForbidden();
    }

    #[Test]
    public function an_administrator_can_read_dashboard_usage_and_user_contracts(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        User::factory()->create(['name' => 'عميل البحث', 'email' => 'search@example.com']);
        Sanctum::actingAs($admin);

        $this->getJson(route('api.v1.admin.dashboard'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'stats' => [
                        'users',
                        'tools_live',
                        'tools_total',
                        'runs',
                        'runs_completed',
                        'runs_failed',
                        'reports',
                        'ai_cost_usd',
                        'ai_calls',
                    ],
                    'recent_runs',
                ],
            ]);

        $this->getJson(route('api.v1.admin.usage', ['days' => 14]))
            ->assertOk()
            ->assertJsonPath('data.days', 14)
            ->assertJsonStructure(['data' => ['totals', 'by_model', 'by_stage', 'tools']]);

        $this->getJson(route('api.v1.admin.users.index', ['q' => 'search@example.com']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'search@example.com')
            ->assertJsonStructure(['meta' => ['query', 'limit']]);
    }

    #[Test]
    public function an_administrator_can_read_every_management_catalog_without_secret_values(): void
    {
        Setting::put('openai.api_key', 'must-never-leak', 'admin', 'secret');

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $this->getJson(route('api.v1.admin.tools.index'))
            ->assertOk()
            ->assertJsonStructure(['data' => [['key', 'title', 'status', 'current_version']]]);

        $this->getJson(route('api.v1.admin.catalog'))
            ->assertOk()
            ->assertJsonStructure(['data' => ['features', 'plans', 'packs', 'gateways']])
            ->assertJsonMissing(['credentials' => 'must-never-leak']);

        $this->getJson(route('api.v1.admin.payments.index'))
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['limit']]);

        $this->getJson(route('api.v1.admin.manual-reports.index'))
            ->assertOk()
            ->assertJsonStructure(['data' => ['pending', 'completed']]);

        $response = $this->getJson(route('api.v1.admin.settings.index'))
            ->assertOk()
            ->assertJsonStructure(['data' => [['group', 'fields']]])
            ->assertJsonMissing(['value' => 'must-never-leak']);

        $this->assertStringNotContainsString('must-never-leak', $response->getContent());
    }

    #[Test]
    public function sensitive_user_mutations_require_confirmation_and_prevent_self_demotion(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create();
        Sanctum::actingAs($admin);

        $this->patchJson(route('api.v1.admin.users.admin', $customer))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('confirmation');

        $this->patchJson(route('api.v1.admin.users.admin', $admin), ['confirmation' => true])
            ->assertConflict();

        $this->patchJson(route('api.v1.admin.users.admin', $customer), ['confirmation' => true])
            ->assertOk()
            ->assertJsonPath('data.is_admin', true);

        $before = $customer->primaryWorkspace()->wallet->balance;

        $this->postJson(route('api.v1.admin.users.credits', $customer), [
            'credits' => 25,
            'confirmation' => true,
        ])->assertOk()->assertJsonPath('data.balance', $before + 25);
    }

    #[Test]
    public function locked_prompts_cannot_be_changed_through_the_api(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $tool = Tool::runnable()->with('currentVersion.prompts')->firstOrFail();
        $prompt = $tool->currentVersion->prompts->firstOrFail();
        $prompt->lock();

        $this->putJson(route('api.v1.admin.tools.prompts.update', [$tool, $prompt]), [
            'content' => 'محتوى جديد مكتمل لا ينبغي حفظه.',
            'tier' => 'standard',
        ])->assertConflict();

        $this->assertNotSame('محتوى جديد مكتمل لا ينبغي حفظه.', $prompt->fresh()->content);
    }

    #[Test]
    public function an_administrator_can_manage_a_credit_pack_and_secrets_stay_masked(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $packId = $this->postJson(route('api.v1.admin.packs.store'), [
            'name' => 'حزمة اختبار الإدارة',
            'credits' => 40,
            'price' => 120,
            'currency' => 'SAR',
            'is_active' => true,
            'sort_order' => 90,
        ])->assertCreated()->json('data.id');

        $this->putJson(route('api.v1.admin.packs.update', $packId), [
            'name' => 'حزمة اختبار محدّثة',
            'credits' => 50,
            'price' => 140,
            'currency' => 'SAR',
            'is_active' => true,
            'sort_order' => 91,
        ])->assertOk()->assertJsonPath('data.credits', 50);

        $this->deleteJson(route('api.v1.admin.packs.destroy', $packId))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('confirmation');

        $this->deleteJson(route('api.v1.admin.packs.destroy', $packId), ['confirmation' => true])
            ->assertOk();

        $response = $this->putJson(route('api.v1.admin.settings.update'), [
            'ai__deepseek__api_key' => 'new-secret-value',
        ])->assertOk();

        $this->assertSame('new-secret-value', Setting::get('ai.deepseek.api_key'));
        $this->assertStringNotContainsString('new-secret-value', $response->getContent());
    }

    #[Test]
    public function a_manual_payment_is_approved_once_through_the_api(): void
    {
        $customer = User::factory()->create();
        $pack = CreditPack::active()->firstOrFail();
        $payment = Payment::create([
            'workspace_id' => $customer->primaryWorkspace()->id,
            'user_id' => $customer->id,
            'provider' => 'manual',
            'purpose' => 'credit_pack',
            'credit_pack_id' => $pack->id,
            'amount' => $pack->price,
            'currency' => $pack->currency,
            'credits_granted' => $pack->credits,
            'status' => Payment::STATUS_PENDING,
        ]);
        $before = $customer->primaryWorkspace()->wallet->balance;

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $this->postJson(route('api.v1.admin.payments.approve', $payment), [
            'confirmation' => true,
        ])->assertOk()->assertJsonPath('data.status', Payment::STATUS_PAID);

        $this->postJson(route('api.v1.admin.payments.approve', $payment), [
            'confirmation' => true,
        ])->assertConflict();

        $this->assertSame(
            $before + $pack->credits,
            $customer->primaryWorkspace()->wallet->fresh()->balance,
        );
    }
}
