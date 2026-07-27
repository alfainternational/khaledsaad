<?php

namespace Tests\Feature;

use App\Models\Tool;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    #[Test]
    public function a_normal_user_cannot_reach_the_admin_panel(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.dashboard'))
            ->assertNotFound();
    }

    #[Test]
    public function an_admin_sees_the_dashboard(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('نظرة عامة')
            ->assertSee('data-layout="dashboard"', false)
            ->assertSee('layout-metrics', false)
            ->assertSee('layout-main-aside', false);
    }

    #[Test]
    public function an_admin_can_toggle_a_tool_status(): void
    {
        $tool = Tool::where('key', 'marketing-score')->firstOrFail();
        $this->assertTrue($tool->isRunnable());

        $this->actingAs($this->admin())
            ->patch(route('admin.tools.status', $tool->key), ['status' => 'coming_soon'])
            ->assertRedirect();

        $this->assertSame('coming_soon', $tool->fresh()->status);
    }

    #[Test]
    public function an_admin_can_grant_credits_to_a_user(): void
    {
        $user = User::factory()->create();
        $wallet = $user->primaryWorkspace()->wallet;
        $before = $wallet->balance;

        $this->actingAs($this->admin())
            ->post(route('admin.users.credits', $user->id), ['credits' => 100])
            ->assertRedirect();

        $this->assertSame($before + 100, $wallet->fresh()->balance);
    }

    #[Test]
    public function the_admin_flag_cannot_be_mass_assigned(): void
    {
        // is_admin ليس في fillable، فلا يمكن رفع الصلاحية عبر تسجيل عادي.
        $user = User::create([
            'name' => 'محاول',
            'email' => 'try@example.test',
            'password' => 'password-1234',
            'is_admin' => true,
        ]);

        $this->assertFalse($user->fresh()->isAdmin());
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }
}
