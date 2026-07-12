<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_chunk_id')->constrained('knowledge_chunks')->cascadeOnDelete();
            $table->string('model_name', 120);
            $table->string('model_version', 120);
            $table->unsignedSmallInteger('dimensions');
            $table->char('content_hash', 64);
            $table->json('vector_json');
            $table->string('status', 24)->default('active');
            $table->timestamps();

            $table->unique(['knowledge_chunk_id', 'model_name', 'model_version'], 'knowledge_embeddings_model_unique');
            $table->index(['model_name', 'model_version', 'status'], 'knowledge_embeddings_active_model');
            $table->index(['content_hash', 'status']);
        });

        Schema::create('knowledge_query_embeddings', function (Blueprint $table) {
            $table->id();
            $table->string('scope_key', 96);
            $table->char('query_hash', 64);
            $table->string('model_name', 120);
            $table->string('model_version', 120);
            $table->unsignedSmallInteger('dimensions');
            $table->json('vector_json');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['scope_key', 'query_hash', 'model_name', 'model_version'], 'knowledge_query_embeddings_unique');
            $table->index('expires_at');
        });

        Schema::create('intelligence_evaluation_cases', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('scope_key', 96);
            $table->foreignId('account_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('visibility', 24);
            $table->text('query');
            $table->foreignId('expected_chunk_id')->nullable()->constrained('knowledge_chunks')->nullOnDelete();
            $table->string('expected_source_uri', 2048)->nullable();
            $table->unsignedTinyInteger('minimum_rank')->default(5);
            $table->string('status', 24)->default('active');
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->index(['scope_key', 'status']);
        });

        Schema::create('intelligence_evaluation_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('engine', 48);
            $table->string('model_name', 120)->nullable();
            $table->string('model_version', 120)->nullable();
            $table->unsignedSmallInteger('case_count')->default(0);
            $table->decimal('recall_at_k', 8, 6)->default(0);
            $table->decimal('mean_reciprocal_rank', 8, 6)->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->string('status', 24);
            $table->json('meta_json')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['engine', 'status', 'completed_at'], 'intelligence_evaluation_runs_latest');
        });

        Schema::create('intelligence_evaluation_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intelligence_evaluation_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('intelligence_evaluation_case_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('rank')->nullable();
            $table->decimal('reciprocal_rank', 8, 6)->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->boolean('passed')->default(false);
            $table->json('diagnostics_json')->nullable();
            $table->timestamps();

            $table->unique(['intelligence_evaluation_run_id', 'intelligence_evaluation_case_id'], 'intelligence_evaluation_result_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intelligence_evaluation_results');
        Schema::dropIfExists('intelligence_evaluation_runs');
        Schema::dropIfExists('intelligence_evaluation_cases');
        Schema::dropIfExists('knowledge_query_embeddings');
        Schema::dropIfExists('knowledge_embeddings');
    }
};
