<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recommendations', function (Blueprint $table): void {
            $table->text('root_cause')->nullable()->after('description');
            $table->text('commercial_impact')->nullable()->after('root_cause');
            $table->json('action_steps')->nullable()->after('commercial_impact');
            $table->string('owner_role')->nullable()->after('action_steps');
            $table->json('resources')->nullable()->after('owner_role');
            $table->string('timeframe')->nullable()->after('resources');
            $table->json('dependencies')->nullable()->after('timeframe');
            $table->string('kpi_definition')->nullable()->after('kpi_hint');
            $table->string('kpi_source')->nullable()->after('kpi_definition');
            $table->string('baseline')->nullable()->after('kpi_source');
            $table->string('target')->nullable()->after('baseline');
            $table->text('missing_baseline_reason')->nullable()->after('target');
            $table->text('success_condition')->nullable()->after('missing_baseline_reason');
            $table->text('stop_condition')->nullable()->after('success_condition');
            $table->json('risks')->nullable()->after('stop_condition');
            $table->unsignedTinyInteger('confidence')->default(50)->after('risks');
        });
    }

    public function down(): void
    {
        Schema::table('recommendations', function (Blueprint $table): void {
            $table->dropColumn([
                'root_cause', 'commercial_impact', 'action_steps', 'owner_role', 'resources',
                'timeframe', 'dependencies', 'kpi_definition', 'kpi_source', 'baseline', 'target',
                'missing_baseline_reason', 'success_condition', 'stop_condition', 'risks', 'confidence',
            ]);
        });
    }
};
