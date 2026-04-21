<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id')->unique();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('billing_email');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
            $table->index('status');
        });

        Schema::create('account_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->default('member');
            $table->string('status')->default('active');
            $table->timestamp('invited_at')->nullable();
            $table->timestamps();
            $table->unique(['account_id', 'user_id']);
        });

        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id')->unique();
            $table->string('code')->unique();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->decimal('monthly_price', 10, 2)->default(0);
            $table->string('status')->default('active');
            $table->json('features_json')->nullable();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->string('status')->default('active');
            $table->timestamp('current_period_end')->nullable();
            $table->timestamps();
            $table->index(['account_id', 'status']);
        });

        Schema::create('workspaces', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id')->unique();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('personal');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['account_id', 'type']);
        });

        Schema::create('workspace_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->default('viewer');
            $table->string('status')->default('active');
            $table->timestamp('invited_at')->nullable();
            $table->timestamps();
            $table->unique(['workspace_id', 'user_id']);
            $table->index(['workspace_id', 'role']);
        });

        Schema::create('clients', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id')->unique();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('name');
            $table->json('contact_info')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['workspace_id', 'status']);
        });

        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id')->unique();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('stage')->default(1);
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['workspace_id', 'stage']);
        });

        Schema::create('tools', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->unsignedTinyInteger('stage');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status')->default('hidden');
            $table->timestamps();
            $table->index(['stage', 'sort_order']);
        });

        Schema::create('tool_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id')->unique();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('tool_code');
            $table->string('mode')->default('quick');
            $table->json('inputs_json')->nullable();
            $table->json('output_json')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['workspace_id', 'project_id']);
            $table->index(['tool_code', 'mode']);
        });

        Schema::create('workspace_data', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('key');
            $table->json('value_json');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['workspace_id', 'project_id']);
            $table->unique(['workspace_id', 'project_id', 'key']);
        });

        Schema::create('approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('item_type');
            $table->unsignedBigInteger('item_id');
            $table->string('status')->default('pending');
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'project_id']);
        });

        Schema::create('entitlements', function (Blueprint $table): void {
            $table->id();
            $table->string('scope_type');
            $table->unsignedBigInteger('scope_id');
            $table->string('key');
            $table->string('value_type')->default('boolean');
            $table->json('value');
            $table->string('source')->default('plan_default');
            $table->timestamps();
            $table->index(['scope_type', 'scope_id']);
            $table->index('key');
            $table->unique(['scope_type', 'scope_id', 'key']);
        });

        Schema::create('feature_flags', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id')->unique();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('module')->nullable();
            $table->string('status')->default('off');
            $table->unsignedTinyInteger('rollout_percentage')->default(100);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index('key');
            $table->index('status');
        });

        Schema::create('feature_flag_audiences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('feature_flag_id')->constrained('feature_flags')->cascadeOnDelete();
            $table->string('audience_type');
            $table->unsignedBigInteger('audience_id');
            $table->timestamps();
            $table->unique(['feature_flag_id', 'audience_type', 'audience_id'], 'flag_audience_unique');
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained('workspaces')->nullOnDelete();
            $table->string('action');
            $table->string('target_type');
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('action');
            $table->index('target_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('feature_flag_audiences');
        Schema::dropIfExists('feature_flags');
        Schema::dropIfExists('entitlements');
        Schema::dropIfExists('approvals');
        Schema::dropIfExists('workspace_data');
        Schema::dropIfExists('tool_runs');
        Schema::dropIfExists('tools');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('workspace_members');
        Schema::dropIfExists('workspaces');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('account_members');
        Schema::dropIfExists('accounts');
    }
};
