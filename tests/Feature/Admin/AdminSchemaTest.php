<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminSchemaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function platform_admin_tables_and_columns_exist(): void
    {
        foreach ([
            'accounts',
            'account_members',
            'workspaces',
            'workspace_members',
            'plans',
            'subscriptions',
            'entitlements',
            'feature_flags',
            'feature_flag_audiences',
            'audit_logs',
            'projects',
            'clients',
            'tools',
            'tool_runs',
            'workspace_data',
            'approvals',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Failed asserting that table [{$table}] exists.");
        }

        foreach (['public_id', 'locale', 'status', 'is_super_admin', 'last_login_at'] as $column) {
            $this->assertTrue(Schema::hasColumn('users', $column));
        }
    }
}
