<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_runs', function (Blueprint $table): void {
            $table->timestamp('failed_at')->nullable()->after('completed_at');
            $table->json('error_json')->nullable()->after('payload_json');
            $table->index(['project_id', 'failed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_runs', function (Blueprint $table): void {
            $table->dropIndex(['project_id', 'failed_at']);
            $table->dropColumn(['failed_at', 'error_json']);
        });
    }
};
