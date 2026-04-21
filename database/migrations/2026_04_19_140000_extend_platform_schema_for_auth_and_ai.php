<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->softDeletes()->after('updated_at');
        });

        Schema::create('ai_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->longText('prompt_template');
            $table->string('model')->default('gpt-5');
            $table->unsignedInteger('credit_cost')->default(1);
            $table->string('status')->default('draft');
            $table->string('module')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_generations', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id')->unique();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained('workspaces')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('ai_templates')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('inputs_json')->nullable();
            $table->longText('output')->nullable();
            $table->unsignedInteger('tokens_used')->default(0);
            $table->string('status')->default('pending');
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['account_id', 'status']);
        });

        Schema::create('ai_credits_ledger', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->integer('delta');
            $table->string('reason');
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['account_id', 'created_at']);
        });

        Schema::create('comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->nullable()->constrained('workspaces')->nullOnDelete();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
        Schema::dropIfExists('ai_credits_ledger');
        Schema::dropIfExists('ai_generations');
        Schema::dropIfExists('ai_templates');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
