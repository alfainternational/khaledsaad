<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_learning_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('active');
            $table->string('current_exercise_key')->nullable();
            $table->unsignedSmallInteger('completed_exercises')->default(0);
            $table->unsignedTinyInteger('average_score')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('marketing_exercise_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('marketing_learning_run_id')->constrained()->cascadeOnDelete();
            $table->string('exercise_key');
            $table->unsignedSmallInteger('revision')->default(0);
            $table->json('answers');
            $table->string('status', 20)->default('draft');
            $table->unsignedTinyInteger('completeness_score')->nullable();
            $table->unsignedTinyInteger('ai_score')->nullable();
            $table->unsignedTinyInteger('final_score')->nullable();
            $table->json('feedback')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();

            $table->unique(['marketing_learning_run_id', 'exercise_key'], 'marketing_attempt_run_exercise_unique');
            $table->index(['status', 'updated_at']);
        });

        Schema::create('marketing_exercise_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('marketing_exercise_attempt_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('revision');
            $table->json('answers');
            $table->unsignedTinyInteger('completeness_score');
            $table->unsignedTinyInteger('ai_score');
            $table->unsignedTinyInteger('final_score');
            $table->json('feedback');
            $table->unsignedSmallInteger('catalog_version');
            $table->timestamp('reviewed_at');
            $table->timestamps();

            $table->unique(['marketing_exercise_attempt_id', 'revision'], 'marketing_review_attempt_revision_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_exercise_reviews');
        Schema::dropIfExists('marketing_exercise_attempts');
        Schema::dropIfExists('marketing_learning_runs');
    }
};
