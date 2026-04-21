<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tools', function (Blueprint $table): void {
            $table->json('audience_types_json')->nullable()->after('module');
            $table->json('goal_tags_json')->nullable()->after('audience_types_json');
            $table->json('awareness_levels_json')->nullable()->after('goal_tags_json');
            $table->string('output_type')->nullable()->after('awareness_levels_json');
            $table->unsignedSmallInteger('estimated_minutes')->nullable()->after('output_type');
            $table->boolean('has_guided_mode')->default(true)->after('estimated_minutes');
            $table->boolean('has_structured_mode')->default(true)->after('has_guided_mode');
            $table->boolean('has_expert_mode')->default(false)->after('has_structured_mode');
            $table->json('next_actions_json')->nullable()->after('has_expert_mode');
            $table->json('depends_on_json')->nullable()->after('next_actions_json');
            $table->json('feeds_into_json')->nullable()->after('depends_on_json');
        });
    }

    public function down(): void
    {
        Schema::table('tools', function (Blueprint $table): void {
            $table->dropColumn([
                'audience_types_json',
                'goal_tags_json',
                'awareness_levels_json',
                'output_type',
                'estimated_minutes',
                'has_guided_mode',
                'has_structured_mode',
                'has_expert_mode',
                'next_actions_json',
                'depends_on_json',
                'feeds_into_json',
            ]);
        });
    }
};
