<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->char('scope_key', 64);
            $table->string('kind', 40);
            $table->text('canonical_uri')->nullable();
            $table->char('identity_hash', 64);
            $table->unsignedTinyInteger('trust_score')->default(50);
            $table->string('visibility', 20)->default('project');
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->unique(['scope_key', 'identity_hash'], 'knowledge_sources_scope_identity_unique');
            $table->index(['project_id', 'kind']);
        });

        Schema::create('knowledge_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_source_id')->constrained()->cascadeOnDelete();
            $table->char('content_hash', 64);
            $table->unsignedInteger('version')->default(1);
            $table->string('title')->nullable();
            $table->string('language', 12)->default('ar');
            $table->string('status', 24)->default('pending');
            $table->longText('content')->nullable();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->unique(['knowledge_source_id', 'content_hash']);
            $table->index(['knowledge_source_id', 'status', 'version']);
        });

        Schema::create('knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('heading')->nullable();
            $table->longText('content');
            $table->unsignedInteger('token_count')->default(0);
            $table->json('locator_json')->nullable();
            $table->timestamps();

            $table->unique(['knowledge_document_id', 'position']);
        });

        if (DB::getDriverName() === 'mysql') {
            Schema::table('knowledge_chunks', function (Blueprint $table) {
                $table->fullText('content', 'knowledge_chunks_content_fulltext');
            });
        }

        Schema::create('knowledge_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->char('scope_key', 64);
            $table->text('statement');
            $table->char('statement_hash', 64);
            $table->string('claim_type', 32)->default('fact');
            $table->decimal('confidence', 5, 2)->default(0);
            $table->string('review_status', 24)->default('candidate');
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->unique(['scope_key', 'statement_hash'], 'knowledge_claims_scope_statement_unique');
            $table->index(['project_id', 'review_status']);
        });

        Schema::create('knowledge_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_claim_id')->constrained()->cascadeOnDelete();
            $table->foreignId('knowledge_chunk_id')->constrained()->cascadeOnDelete();
            $table->string('relation', 20)->default('supports');
            $table->text('quote')->nullable();
            $table->json('locator_json')->nullable();
            $table->timestamps();

            $table->unique(
                ['knowledge_claim_id', 'knowledge_chunk_id', 'relation'],
                'knowledge_evidence_unique'
            );
        });

        Schema::create('knowledge_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_claim_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision', 20);
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['knowledge_claim_id', 'created_at']);
        });

        Schema::create('intelligence_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 48);
            $table->string('status', 24)->default('queued');
            $table->json('payload_json');
            $table->json('result_json')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('leased_until')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'available_at']);
            $table->index(['workspace_id', 'project_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intelligence_jobs');
        Schema::dropIfExists('knowledge_reviews');
        Schema::dropIfExists('knowledge_evidence');
        Schema::dropIfExists('knowledge_claims');
        Schema::dropIfExists('knowledge_chunks');
        Schema::dropIfExists('knowledge_documents');
        Schema::dropIfExists('knowledge_sources');
    }
};
