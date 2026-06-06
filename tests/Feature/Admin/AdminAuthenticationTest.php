<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\PlatformBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function super_admin_can_log_in_to_admin_panel(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);

        $response = $this->post(route('admin.login.store'), [
            'email' => config('platform.admin.email'),
            'password' => config('platform.admin.password'),
        ]);

        $response->assertRedirectToRoute('admin.dashboard');
        $this->assertAuthenticated();
    }

    #[Test]
    public function non_admin_user_cannot_access_admin_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }
}
