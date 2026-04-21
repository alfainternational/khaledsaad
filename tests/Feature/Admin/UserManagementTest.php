<?php

namespace Tests\Feature\Admin;

use App\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\PlatformBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_create_update_and_delete_users(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);
        $admin = User::query()->where('is_super_admin', true)->firstOrFail();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Editor User',
            'email' => 'editor@example.com',
            'password' => 'Secret123!',
            'locale' => 'ar',
            'status' => UserStatus::Active->value,
            'is_super_admin' => false,
        ])->assertRedirect();

        $user = User::query()->where('email', 'editor@example.com')->firstOrFail();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.user.created',
            'target_type' => 'user',
            'target_id' => $user->id,
        ]);

        $this->actingAs($admin)->put(route('admin.users.update', $user), [
            'name' => 'Editor User Updated',
            'email' => 'editor@example.com',
            'password' => '',
            'locale' => 'en',
            'status' => UserStatus::Frozen->value,
            'is_super_admin' => false,
        ])->assertSessionHas('status');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Editor User Updated',
            'locale' => 'en',
            'status' => UserStatus::Frozen->value,
        ]);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $user))
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.user.deleted',
            'target_type' => 'user',
            'target_id' => $user->id,
        ]);
    }

    #[Test]
    public function admin_can_freeze_unfreeze_and_reset_password_for_users(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);
        $admin = User::query()->where('is_super_admin', true)->firstOrFail();
        $user = User::factory()->create();

        $this->actingAs($admin)->patch(route('admin.users.status', $user), [
            'status' => UserStatus::Frozen->value,
        ])->assertSessionHas('status');

        $user->refresh();
        $this->assertSame(UserStatus::Frozen, $user->status);

        $this->actingAs($admin)->post(route('admin.users.reset-password', $user))
            ->assertSessionHas('temporary_password');

        $this->actingAs($admin)->patch(route('admin.users.status', $user), [
            'status' => UserStatus::Active->value,
        ])->assertSessionHas('status');

        $this->assertSame(UserStatus::Active, $user->refresh()->status);
    }

    #[Test]
    public function admin_cannot_freeze_the_last_active_super_admin(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);
        $admin = User::query()->where('is_super_admin', true)->firstOrFail();

        $this->actingAs($admin)->patch(route('admin.users.status', $admin), [
            'status' => UserStatus::Frozen->value,
        ])->assertStatus(422);
    }
}
