<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tool_runs', function (Blueprint $table): void {
            $table->json('summary_json')->nullable()->after('output_json');
            $table->json('next_actions_json')->nullable()->after('summary_json');
            $table->json('source_context_json')->nullable()->after('next_actions_json');
            $table->unsignedTinyInteger('completeness_score')->default(0)->after('source_context_json');
        });
    }

    public function down(): void
    {
        Schema::table('tool_runs', function (Blueprint $table): void {
            $table->dropColumn([
                'summary_json',
                'next_actions_json',
                'source_context_json',
                'completeness_score',
            ]);
        });
    }
};
