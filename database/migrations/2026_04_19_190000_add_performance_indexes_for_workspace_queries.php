<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->index(['is_super_admin', 'status'], 'users_admin_status_idx');
        });

        Schema::table('workspace_members', function (Blueprint $table): void {
            $table->index(['user_id', 'status'], 'workspace_members_user_status_idx');
            $table->index(['workspace_id', 'status', 'role'], 'workspace_members_workspace_status_role_idx');
        });

        Schema::table('clients', function (Blueprint $table): void {
            $table->index(['workspace_id', 'updated_at'], 'clients_workspace_updated_idx');
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->index(['workspace_id', 'status'], 'projects_workspace_status_idx');
            $table->index(['workspace_id', 'updated_at'], 'projects_workspace_updated_idx');
        });

        Schema::table('tool_runs', function (Blueprint $table): void {
            $table->index(['project_id', 'created_at'], 'tool_runs_project_created_idx');
            $table->index(['workspace_id', 'created_at'], 'tool_runs_workspace_created_idx');
            $table->index(['workspace_id', 'tool_code', 'created_at'], 'tool_runs_workspace_tool_created_idx');
        });

        Schema::table('workspace_data', function (Blueprint $table): void {
            $table->index(['workspace_id', 'key'], 'workspace_data_workspace_key_idx');
        });

        Schema::table('approvals', function (Blueprint $table): void {
            $table->index(['workspace_id', 'created_at'], 'approvals_workspace_created_idx');
            $table->index(['workspace_id', 'status', 'created_at'], 'approvals_workspace_status_created_idx');
            $table->index(['project_id', 'status'], 'approvals_project_status_idx');
        });

        Schema::table('ai_templates', function (Blueprint $table): void {
            $table->index(['status', 'credit_cost'], 'ai_templates_status_credit_idx');
        });

        Schema::table('ai_generations', function (Blueprint $table): void {
            $table->index(['workspace_id', 'created_at'], 'ai_generations_workspace_created_idx');
        });

        Schema::table('workspace_invitations', function (Blueprint $table): void {
            $table->index(['workspace_id', 'created_at'], 'workspace_invitations_workspace_created_idx');
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->index(['workspace_id', 'created_at'], 'audit_logs_workspace_created_idx');
            $table->index(['actor_user_id', 'created_at'], 'audit_logs_actor_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex('audit_logs_actor_created_idx');
            $table->dropIndex('audit_logs_workspace_created_idx');
        });

        Schema::table('workspace_invitations', function (Blueprint $table): void {
            $table->dropIndex('workspace_invitations_workspace_created_idx');
        });

        Schema::table('ai_generations', function (Blueprint $table): void {
            $table->dropIndex('ai_generations_workspace_created_idx');
        });

        Schema::table('ai_templates', function (Blueprint $table): void {
            $table->dropIndex('ai_templates_status_credit_idx');
        });

        Schema::table('approvals', function (Blueprint $table): void {
            $table->dropIndex('approvals_project_status_idx');
            $table->dropIndex('approvals_workspace_status_created_idx');
            $table->dropIndex('approvals_workspace_created_idx');
        });

        Schema::table('workspace_data', function (Blueprint $table): void {
            $table->dropIndex('workspace_data_workspace_key_idx');
        });

        Schema::table('tool_runs', function (Blueprint $table): void {
            $table->dropIndex('tool_runs_workspace_tool_created_idx');
            $table->dropIndex('tool_runs_workspace_created_idx');
            $table->dropIndex('tool_runs_project_created_idx');
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropIndex('projects_workspace_updated_idx');
            $table->dropIndex('projects_workspace_status_idx');
        });

        Schema::table('clients', function (Blueprint $table): void {
            $table->dropIndex('clients_workspace_updated_idx');
        });

        Schema::table('workspace_members', function (Blueprint $table): void {
            $table->dropIndex('workspace_members_workspace_status_role_idx');
            $table->dropIndex('workspace_members_user_status_idx');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_admin_status_idx');
        });
    }
};
