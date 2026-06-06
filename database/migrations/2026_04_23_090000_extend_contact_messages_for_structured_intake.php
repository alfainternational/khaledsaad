<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table): void {
            $table->string('message_type')->default('general')->after('body');
            $table->string('source')->default('website')->after('message_type');
            $table->json('payload')->nullable()->after('source');
            $table->foreignId('converted_workspace_id')->nullable()->after('read_at')->constrained('workspaces')->nullOnDelete();
            $table->foreignId('converted_client_id')->nullable()->after('converted_workspace_id')->constrained('clients')->nullOnDelete();
            $table->foreignId('converted_project_id')->nullable()->after('converted_client_id')->constrained('projects')->nullOnDelete();
            $table->timestamp('converted_at')->nullable()->after('converted_project_id');
            $table->index('message_type');
        });
    }

    public function down(): void
    {
        Schema::table('contact_messages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('converted_project_id');
            $table->dropConstrainedForeignId('converted_client_id');
            $table->dropConstrainedForeignId('converted_workspace_id');
            $table->dropColumn([
                'message_type',
                'source',
                'payload',
                'converted_at',
            ]);
        });
    }
};
