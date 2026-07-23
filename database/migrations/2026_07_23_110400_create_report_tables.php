<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tool_run_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('status')->default('draft');
            $table->unsignedTinyInteger('score')->nullable();
            $table->string('score_band')->nullable();
            $table->text('summary')->nullable();
            $table->json('assumptions')->nullable();
            $table->json('next_step')->nullable();
            // بصمة اللحظة: أي نموذج وأي إصدار أنتج هذا التقرير.
            $table->string('generated_by_model')->nullable();
            $table->unsignedSmallInteger('tool_version')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'created_at']);
        });

        Schema::create('report_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('title');
            $table->json('content_json');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['report_id', 'key']);
        });

        Schema::create('findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('title');
            $table->text('description');
            $table->string('severity');
            $table->text('evidence')->nullable();
            $table->unsignedTinyInteger('confidence')->default(50);
            // BR-007: كل نتيجة بلا دليل تُصنف افتراضًا لا حقيقة.
            $table->boolean('is_assumption')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finding_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('impact');
            $table->string('effort');
            $table->unsignedTinyInteger('priority')->default(50);
            $table->string('kpi_hint')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendations');
        Schema::dropIfExists('findings');
        Schema::dropIfExists('report_sections');
        Schema::dropIfExists('reports');
    }
};
