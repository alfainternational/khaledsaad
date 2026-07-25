<?php

namespace Tests\Feature;

use App\Models\Setting;
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
}
