<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_learning_runs', function (Blueprint $table): void {
            $table->foreignId('workspace_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        DB::table('marketing_learning_runs')
            ->whereNull('workspace_id')
            ->orderBy('id')
            ->eachById(function (object $run): void {
                $workspaceId = DB::table('projects')->where('id', $run->project_id)->value('workspace_id');

                if ($workspaceId !== null) {
                    DB::table('marketing_learning_runs')->where('id', $run->id)->update([
                        'workspace_id' => $workspaceId,
                    ]);
                }
            });

        Schema::table('marketing_learning_runs', function (Blueprint $table): void {
            $table->foreignId('project_id')->nullable()->change();
            $table->index(['workspace_id', 'started_by'], 'marketing_learning_workspace_starter_index');
        });
    }

    public function down(): void
    {
        DB::table('marketing_learning_runs')->whereNull('project_id')->delete();

        Schema::table('marketing_learning_runs', function (Blueprint $table): void {
            $table->dropIndex('marketing_learning_workspace_starter_index');
            $table->dropConstrainedForeignId('workspace_id');
            $table->foreignId('project_id')->nullable(false)->change();
        });
    }
};
