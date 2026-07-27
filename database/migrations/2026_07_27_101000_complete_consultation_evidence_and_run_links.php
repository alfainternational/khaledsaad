<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultation_evidence', function (Blueprint $table): void {
            $table->string('disk')->default('local')->after('source_locator');
            $table->string('mime_type')->nullable()->after('disk');
            $table->unsignedBigInteger('size_bytes')->nullable()->after('mime_type');
            $table->string('extraction_status')->default('pending')->after('size_bytes');
            $table->mediumText('extracted_text')->nullable()->after('extraction_status');
            $table->string('sha256', 64)->nullable()->after('extracted_text');
        });

        Schema::table('tool_runs', function (Blueprint $table): void {
            $table->foreignId('consultation_session_id')->nullable()->after('project_id')
                ->constrained()->nullOnDelete();
            $table->index(['consultation_session_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('tool_runs', function (Blueprint $table): void {
            $table->dropIndex(['consultation_session_id', 'status']);
            $table->dropConstrainedForeignId('consultation_session_id');
        });

        Schema::table('consultation_evidence', function (Blueprint $table): void {
            $table->dropColumn([
                'disk', 'mime_type', 'size_bytes', 'extraction_status', 'extracted_text', 'sha256',
            ]);
        });
    }
};
