<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_uploads', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 40)->unique();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('knowledge_source_id')->nullable()->constrained('knowledge_sources')->nullOnDelete();
            $table->string('disk', 40)->default('local');
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->string('extension', 20)->nullable();
            $table->unsignedBigInteger('byte_size');
            $table->char('sha256', 64);
            $table->string('status', 24)->default('stored');
            $table->string('error_code', 64)->nullable();
            $table->json('extraction_meta_json')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'sha256'], 'knowledge_uploads_project_hash_unique');
            $table->index(['workspace_id', 'project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_uploads');
    }
};
