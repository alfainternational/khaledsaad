<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_research_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->text('query');
            $table->char('query_hash', 64);
            $table->string('status', 24)->default('queued');
            $table->unsignedSmallInteger('requested_depth')->default(3);
            $table->unsignedInteger('result_count')->default(0);
            $table->unsignedInteger('verified_count')->default(0);
            $table->unsignedInteger('conflict_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('checkpoint_json')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->timestamps();

            $table->index(['query_hash', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('web_research_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('web_research_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('knowledge_source_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('knowledge_document_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 32);
            $table->unsignedSmallInteger('rank');
            $table->string('title')->nullable();
            $table->text('original_url');
            $table->text('normalized_url');
            $table->char('normalized_url_hash', 64);
            $table->string('domain', 253);
            $table->text('snippet')->nullable();
            $table->char('content_hash', 64)->nullable();
            $table->string('fetch_status', 24)->default('pending');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('trust_tier', 24)->default('unknown');
            $table->unsignedTinyInteger('trust_score')->default(0);
            $table->string('freshness_status', 24)->default('unknown');
            $table->string('verification_status', 24)->default('unverified');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->unique(
                ['web_research_run_id', 'normalized_url_hash'],
                'web_research_results_run_url_unique'
            );
            $table->index(['domain', 'fetched_at']);
            $table->index(['fetch_status', 'valid_until']);
            $table->index(['verification_status', 'trust_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_research_results');
        Schema::dropIfExists('web_research_runs');
    }
};
