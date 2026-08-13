<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('objectives', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('domain');
            $table->text('description');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('recommendation_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('objective_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->string('title');
            $table->json('body');
            $table->json('required_context')->nullable();
            $table->boolean('is_hypothesis')->default(true);
            $table->string('locale', 12)->default('ar');
            $table->unsignedSmallInteger('version')->default(1);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['objective_id', 'active']);
            $table->unique(['objective_id', 'locale', 'version']);
        });

        Schema::create('template_bindings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_id')->constrained('recommendation_templates')->cascadeOnDelete();
            $table->string('field_key');
            $table->string('answer_key');
            $table->string('transform')->default('text');
            $table->timestamps();

            $table->unique(['template_id', 'field_key']);
        });

        Schema::table('reports', function (Blueprint $table): void {
            $table->string('provenance')->default('automated')->after('review_mode');
            $table->decimal('score_raw', 10, 2)->nullable()->after('score');
            $table->decimal('score_max', 10, 2)->nullable()->after('score_raw');
            $table->timestamp('issued_at')->nullable()->after('published_at');
            $table->timestamp('authored_at')->nullable()->after('issued_at');
            $table->foreignId('authored_by')->nullable()->after('authored_at')->constrained('users')->nullOnDelete();
            $table->string('validation_status')->default('pending')->after('authored_by');
            $table->unsignedSmallInteger('schema_version')->default(1)->after('validation_status');
            $table->json('contract_payload')->nullable()->after('schema_version');

            $table->index(['project_id', 'issued_at']);
            $table->index(['validation_status', 'schema_version']);
        });

        Schema::table('findings', function (Blueprint $table): void {
            $table->foreignId('evidence_answer_id')->nullable()->after('evidence')
                ->constrained('tool_run_answers')->nullOnDelete();
            $table->text('evidence_quote')->nullable()->after('evidence_answer_id');
        });

        Schema::table('recommendations', function (Blueprint $table): void {
            $table->foreignId('objective_id')->nullable()->after('report_id')->constrained()->nullOnDelete();
            $table->foreignId('metric_objective_id')->nullable()->after('kpi_hint')->constrained('objectives')->nullOnDelete();
            $table->text('deliverable')->nullable()->after('description');
            $table->text('done_when')->nullable()->after('deliverable');
            $table->text('first_five_minutes')->nullable()->after('done_when');
            $table->text('expected_failure')->nullable()->after('first_five_minutes');
            $table->unsignedSmallInteger('duration_days')->nullable()->after('timeframe');
            $table->foreignId('template_id')->nullable()->after('duration_days')
                ->constrained('recommendation_templates')->nullOnDelete();
            $table->boolean('degraded')->default(false)->after('template_id');
            $table->string('degrade_reason')->nullable()->after('degraded');
            $table->json('fallback_coaching')->nullable()->after('degrade_reason');

            $table->index(['objective_id', 'degraded']);
        });

        Schema::table('agency_reports', function (Blueprint $table): void {
            $table->string('provenance')->default('automated')->after('status');
            $table->string('validation_status')->default('pending')->after('provenance');
            $table->unsignedSmallInteger('schema_version')->default(1)->after('validation_status');
        });

        Schema::create('report_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->string('actor_type');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('diff');
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('validation_findings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->string('rule_code');
            $table->string('severity');
            $table->string('path');
            $table->text('message');
            $table->text('suggested_action')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['report_id', 'severity']);
            $table->index(['rule_code', 'severity']);
        });

        Schema::create('human_traces', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('finding_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->text('body');
            $table->json('meta')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('template_gaps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('objective_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('last_seen_at');
            $table->json('missing_context')->nullable();
            $table->timestamps();

            $table->unique('objective_id');
        });

        Schema::create('scoring_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->string('item_key');
            $table->string('tier')->nullable();
            $table->decimal('weight', 10, 2);
            $table->decimal('coefficient', 8, 4);
            $table->decimal('points', 10, 2);
            $table->json('answer_value')->nullable();
            $table->text('answer_quote')->nullable();
            $table->timestamps();

            $table->unique(['report_id', 'item_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scoring_items');
        Schema::dropIfExists('template_gaps');
        Schema::dropIfExists('human_traces');
        Schema::dropIfExists('validation_findings');
        Schema::dropIfExists('report_revisions');

        Schema::table('agency_reports', function (Blueprint $table): void {
            $table->dropColumn(['provenance', 'validation_status', 'schema_version']);
        });

        Schema::table('recommendations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('template_id');
            $table->dropConstrainedForeignId('metric_objective_id');
            $table->dropConstrainedForeignId('objective_id');
            $table->dropColumn([
                'deliverable', 'done_when', 'first_five_minutes', 'expected_failure',
                'duration_days', 'degraded', 'degrade_reason', 'fallback_coaching',
            ]);
        });

        Schema::table('findings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('evidence_answer_id');
            $table->dropColumn('evidence_quote');
        });

        Schema::table('reports', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('authored_by');
            $table->dropColumn([
                'provenance', 'score_raw', 'score_max', 'issued_at', 'authored_at',
                'validation_status', 'schema_version', 'contract_payload',
            ]);
        });

        Schema::dropIfExists('template_bindings');
        Schema::dropIfExists('recommendation_templates');
        Schema::dropIfExists('objectives');
    }
};
