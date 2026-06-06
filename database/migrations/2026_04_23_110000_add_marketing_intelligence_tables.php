<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->string('sector')->default('general_business')->after('status');
            $table->string('market_country')->nullable()->after('sector');
            $table->string('primary_domain')->nullable()->after('market_country');
            $table->json('official_social_links_json')->nullable()->after('primary_domain');
            $table->json('competitors_json')->nullable()->after('official_social_links_json');
            $table->json('analysis_goals_json')->nullable()->after('competitors_json');
            $table->boolean('monitoring_enabled')->default(false)->after('analysis_goals_json');
            $table->index(['workspace_id', 'sector']);
            $table->index(['workspace_id', 'monitoring_enabled']);
        });

        Schema::create('audit_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id')->unique();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('status')->default('queued');
            $table->string('trigger_source')->default('manual');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('summary_json')->nullable();
            $table->json('report_json')->nullable();
            $table->json('payload_json')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['workspace_id', 'project_id']);
            $table->index(['project_id', 'status']);
        });

        Schema::create('audit_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_run_id')->constrained('audit_runs')->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('kind')->default('primary');
            $table->string('label');
            $table->string('domain')->nullable();
            $table->string('page_url')->nullable();
            $table->json('social_links_json')->nullable();
            $table->string('status')->default('pending');
            $table->json('snapshot_json')->nullable();
            $table->timestamps();
            $table->index(['audit_run_id', 'kind']);
        });

        Schema::create('audit_findings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_run_id')->constrained('audit_runs')->cascadeOnDelete();
            $table->foreignId('audit_target_id')->nullable()->constrained('audit_targets')->nullOnDelete();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('area');
            $table->string('subcategory');
            $table->string('severity')->default('medium');
            $table->decimal('confidence', 5, 2)->default(0);
            $table->integer('score_impact')->default(0);
            $table->string('title');
            $table->text('evidence')->nullable();
            $table->text('recommendation')->nullable();
            $table->text('source_url')->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamps();
            $table->index(['audit_run_id', 'area']);
            $table->index(['project_id', 'severity']);
        });

        Schema::create('scorecards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_run_id')->constrained('audit_runs')->cascadeOnDelete();
            $table->foreignId('audit_target_id')->nullable()->constrained('audit_targets')->nullOnDelete();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('scope')->default('project');
            $table->string('code');
            $table->string('label');
            $table->unsignedTinyInteger('score')->default(0);
            $table->json('meta_json')->nullable();
            $table->timestamps();
            $table->unique(['audit_run_id', 'audit_target_id', 'scope', 'code'], 'scorecards_run_target_scope_code_unique');
            $table->index(['project_id', 'scope']);
        });

        Schema::create('official_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_run_id')->constrained('audit_runs')->cascadeOnDelete();
            $table->foreignId('audit_target_id')->nullable()->constrained('audit_targets')->nullOnDelete();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('contact_type');
            $table->string('contact_value');
            $table->text('source_url')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_primary')->default(false);
            $table->json('meta_json')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'contact_type']);
        });

        Schema::create('monitor_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('audit_run_id')->nullable()->constrained('audit_runs')->nullOnDelete();
            $table->timestamp('captured_at');
            $table->unsignedTinyInteger('executive_score')->default(0);
            $table->unsignedTinyInteger('website_score')->default(0);
            $table->unsignedTinyInteger('social_score')->default(0);
            $table->unsignedTinyInteger('seo_score')->default(0);
            $table->unsignedTinyInteger('trust_score')->default(0);
            $table->unsignedTinyInteger('conversion_score')->default(0);
            $table->unsignedTinyInteger('ads_readiness_score')->default(0);
            $table->unsignedTinyInteger('ai_visibility_score')->default(0);
            $table->unsignedTinyInteger('competition_score')->default(0);
            $table->unsignedTinyInteger('lead_readiness_score')->default(0);
            $table->json('payload_json')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitor_snapshots');
        Schema::dropIfExists('official_contacts');
        Schema::dropIfExists('scorecards');
        Schema::dropIfExists('audit_findings');
        Schema::dropIfExists('audit_targets');
        Schema::dropIfExists('audit_runs');

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropIndex(['workspace_id', 'sector']);
            $table->dropIndex(['workspace_id', 'monitoring_enabled']);
            $table->dropColumn([
                'sector',
                'market_country',
                'primary_domain',
                'official_social_links_json',
                'competitors_json',
                'analysis_goals_json',
                'monitoring_enabled',
            ]);
        });
    }
};
