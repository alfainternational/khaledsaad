<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Tool;
use App\Models\User;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminOperationsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_operations_room_shows_health_funnel_and_audit(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.operations'))
            ->assertOk()
            ->assertSee('قمع التحويل')
            ->assertSee('سجل التدقيق');
    }

    #[Test]
    public function an_admin_can_release_a_tool_version_from_the_panel_and_it_is_audited(): void
    {
        $this->seed(ToolCatalogSeeder::class);
        $admin = User::factory()->create(['is_admin' => true]);
        $tool = Tool::where('key', 'marketing-score')->firstOrFail();
        $before = $tool->versions()->max('version');
        $definition = (string) file_get_contents(database_path('data/tools/marketing-score.php'));

        try {
            $this->actingAs($admin)
                ->post(route('admin.tools.release', $tool->key))
                ->assertRedirect();

            $this->assertSame($before + 1, $tool->versions()->max('version'));
            $this->assertSame('tool.release', AuditLog::latest('id')->value('action'));
        } finally {
            file_put_contents(database_path('data/tools/marketing-score.php'), $definition);
        }
    }

    #[Test]
    public function impersonation_enters_the_user_account_flags_the_session_and_stops_cleanly(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.impersonate', $customer->id))
            ->assertRedirect(route('app.dashboard'));

        $this->assertAuthenticatedAs($customer);
        $this->assertSame($admin->id, session('impersonator_id'));

        $this->post(route('app.impersonation.stop'))->assertRedirect(route('admin.users.index'));
        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session('impersonator_id'));

        $this->assertSame(
            ['impersonation.start', 'impersonation.stop'],
            AuditLog::orderBy('id')->pluck('action')->all(),
        );
    }

    #[Test]
    public function an_admin_account_can_never_be_impersonated(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $otherAdmin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('admin.users.impersonate', $otherAdmin->id))
            ->assertForbidden();
    }
}
