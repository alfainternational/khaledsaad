<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chat_conversations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 160);
            $table->string('tool_key', 100)->default('general');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'user_id', 'last_message_at'], 'ai_chat_conversations_owner_recent_index');
            $table->index(['account_id', 'workspace_id', 'project_id'], 'ai_chat_conversations_scope_index');
        });

        Schema::create('ai_chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('conversation_id')->constrained('ai_chat_conversations')->cascadeOnDelete();
            $table->foreignId('intelligence_job_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role', 16);
            $table->longText('content')->nullable();
            $table->string('status', 24)->default('queued');
            $table->string('client_request_id', 100)->nullable();
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'client_request_id'], 'ai_chat_messages_request_unique');
            $table->index(['conversation_id', 'created_at'], 'ai_chat_messages_conversation_time_index');
            $table->index(['status', 'created_at'], 'ai_chat_messages_status_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_messages');
        Schema::dropIfExists('ai_chat_conversations');
    }
};
