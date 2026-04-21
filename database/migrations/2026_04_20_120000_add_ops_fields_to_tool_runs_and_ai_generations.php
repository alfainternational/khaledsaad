<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tool_runs', function (Blueprint $table): void {
            $table->string('ops_review_status')->nullable()->after('created_by');
            $table->text('ops_note')->nullable()->after('ops_review_status');
            $table->json('ops_tags')->nullable()->after('ops_note');
            $table->index('ops_review_status');
        });

        Schema::table('ai_generations', function (Blueprint $table): void {
            $table->string('ops_review_status')->nullable()->after('error');
            $table->text('ops_note')->nullable()->after('ops_review_status');
            $table->json('ops_tags')->nullable()->after('ops_note');
            $table->index('ops_review_status');
        });
    }

    public function down(): void
    {
        Schema::table('tool_runs', function (Blueprint $table): void {
            $table->dropIndex(['ops_review_status']);
            $table->dropColumn(['ops_review_status', 'ops_note', 'ops_tags']);
        });

        Schema::table('ai_generations', function (Blueprint $table): void {
            $table->dropIndex(['ops_review_status']);
            $table->dropColumn(['ops_review_status', 'ops_note', 'ops_tags']);
        });
    }
};
