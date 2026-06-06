<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Execution layer (Phase ج) — the moat: turn diagnosis findings into recommendations,
 * then into actionable execution packages with outputs, tasks and measurement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommendations', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('workspace_id')->index();
            $table->foreignId('project_id')->index();
            $table->foreignId('audit_finding_id')->nullable()->index();
            $table->string('area', 60)->nullable();
            $table->string('title');
            $table->unsignedSmallInteger('priority')->default(100); // lower = higher priority
            $table->string('severity', 20)->default('medium'); // high|medium|low
            $table->text('evidence')->nullable();
            $table->text('rationale')->nullable();
            $table->string('estimated_impact', 20)->default('medium'); // high|medium|low
            $table->float('confidence')->default(0);
            $table->string('status', 20)->default('proposed'); // proposed|accepted|dismissed
            $table->foreignId('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'project_id', 'status']);
        });

        Schema::create('execution_packages', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('workspace_id')->index();
            $table->foreignId('project_id')->index();
            $table->foreignId('recommendation_id')->nullable()->index();
            $table->string('title');
            $table->text('problem')->nullable();
            $table->text('evidence')->nullable();
            $table->text('decision')->nullable();
            $table->text('measurement_plan')->nullable();
            $table->foreignId('owner_user_id')->nullable();
            // proposed|in_review|approved|in_progress|executed|measuring
            $table->string('status', 20)->default('proposed');
            $table->date('deadline')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'project_id', 'status']);
        });

        Schema::create('execution_tasks', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('execution_package_id')->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('assigned_to')->nullable();
            $table->string('status', 20)->default('pending'); // pending|in_progress|done
            $table->date('due_date')->nullable();
            $table->unsignedSmallInteger('order_index')->default(0);
            $table->timestamps();
        });

        Schema::create('execution_assets', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('execution_package_id')->index();
            // copy|design_brief|dev_brief|ad|measurement|other
            $table->string('type', 30)->default('copy');
            $table->string('title');
            $table->longText('body')->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamps();
        });

        Schema::create('execution_reports', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('execution_package_id')->index();
            // discovery|planning|execution|validation
            $table->string('phase', 20)->default('discovery');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->json('notes_json')->nullable();
            $table->json('metrics_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('execution_reports');
        Schema::dropIfExists('execution_assets');
        Schema::dropIfExists('execution_tasks');
        Schema::dropIfExists('execution_packages');
        Schema::dropIfExists('recommendations');
    }
};
