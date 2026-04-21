<?php

namespace Tests\Feature\Admin;

use App\Domain\Audit\Models\AuditLog;
use App\Models\User;
use Database\Seeders\PlatformBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_actions_are_recorded_and_audit_logs_can_be_exported(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);
        $admin = User::query()->where('is_super_admin', true)->firstOrFail();

        $this->actingAs($admin)->post(route('admin.plans.store'), [
            'code' => 'audit-plan',
            'name_ar' => 'Audit Plan',
            'name_en' => 'Audit Plan',
            'monthly_price' => 99,
            'status' => 'active',
            'entitlements' => [],
        ]);

        $this->assertTrue(AuditLog::query()->where('action', 'admin.plan.created')->exists());

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index', ['action' => 'admin.plan.created']))
            ->assertOk()
            ->assertSee('admin.plan.created');

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.export', ['action' => 'admin.plan.created']))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=audit-logs.csv');
    }
}
