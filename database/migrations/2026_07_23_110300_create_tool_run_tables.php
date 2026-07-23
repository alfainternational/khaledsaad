<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tool_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tool_version_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('guest_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('draft');
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->unsignedTinyInteger('base_score')->nullable();
            $table->unsignedTinyInteger('confidence')->nullable();
            // BR-005: لقطة مجمدة لبيانات المشروع وقت التشغيل حتى يبقى التقرير قابلًا للتكرار.
            $table->json('snapshot')->nullable();
            $table->string('failure_reason')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
        });

        Schema::create('tool_run_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tool_run_id')->constrained()->cascadeOnDelete();
            $table->string('field_key');
            $table->json('value_json')->nullable();
            // user = أدخلها المستخدم | extracted = استُخرجت من مرفق | profile = من ملف المشروع
            $table->string('source')->default('user');
            $table->timestamps();

            $table->unique(['tool_run_id', 'field_key']);
        });

        Schema::create('tool_run_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tool_run_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->string('status')->default('pending');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['tool_run_id', 'key']);
        });

        Schema::create('tool_run_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tool_run_id')->constrained()->cascadeOnDelete();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedInteger('size_bytes');
            $table->string('extraction_status')->default('pending');
            $table->longText('extracted_text')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_run_files');
        Schema::dropIfExists('tool_run_stages');
        Schema::dropIfExists('tool_run_answers');
        Schema::dropIfExists('tool_runs');
    }
};
