<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tool_runs', function (Blueprint $table): void {
            $table->index(
                ['workspace_id', 'project_id', 'tool_code', 'id'],
                'tool_runs_workspace_project_tool_id_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('tool_runs', function (Blueprint $table): void {
            $table->dropIndex('tool_runs_workspace_project_tool_id_idx');
        });
    }
};
