<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_templates', function (Blueprint $table): void {
            $table->string('domain')->nullable()->after('module');
            $table->longText('system_role')->nullable()->after('domain');
            $table->json('output_contract_json')->nullable()->after('system_role');
        });
    }

    public function down(): void
    {
        Schema::table('ai_templates', function (Blueprint $table): void {
            $table->dropColumn(['domain', 'system_role', 'output_contract_json']);
        });
    }
};
