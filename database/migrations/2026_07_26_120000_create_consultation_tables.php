<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultation_blueprints', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->timestamps();
        });

        Schema::create('consultation_blueprint_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('consultation_blueprint_id');
            $table->foreign('consultation_blueprint_id', 'consultation_bp_version_bp_fk')
                ->references('id')->on('consultation_blueprints')->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->string('status')->default('draft');
            $table->json('settings')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
            $table->unique(['consultation_blueprint_id', 'version'], 'consultation_bp_version_unique');
        });

        Schema::table('consultation_blueprints', function (Blueprint $table): void {
            $table->foreign('current_version_id')->references('id')->on('consultation_blueprint_versions')->nullOnDelete();
        });

        Schema::create('diagnostic_modules', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->foreignId('tool_id')->nullable()->constrained()->nullOnDelete();
            $table->json('applicability')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('blueprint_modules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('blueprint_version_id')->constrained('consultation_blueprint_versions')->cascadeOnDelete();
            $table->foreignId('diagnostic_module_id')->constrained()->cascadeOnDelete();
            $table->string('importance')->default('supporting');
            $table->boolean('required')->default(false);
            $table->json('activation_rules')->nullable();
            $table->json('stop_rules')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['blueprint_version_id', 'diagnostic_module_id'], 'blueprint_module_unique');
        });

        Schema::create('question_definitions', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('internal_variable');
            $table->string('sensitivity')->default('normal');
            $table->boolean('inferable')->default(false);
            $table->foreignId('legacy_tool_field_id')->nullable()->constrained('tool_fields')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('question_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('question_definition_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version')->default(1);
            $table->text('user_text');
            $table->text('help_text')->nullable();
            $table->text('why_text')->nullable();
            $table->string('answer_type');
            $table->json('options')->nullable();
            $table->json('validation')->nullable();
            $table->boolean('required')->default(true);
            $table->boolean('allow_unknown')->default(true);
            $table->boolean('allow_skip')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
            $table->unique(['question_definition_id', 'version']);
        });

        Schema::create('module_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('blueprint_module_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_version_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('diagnostic_impact')->default(3);
            $table->unsignedTinyInteger('discrimination')->default(3);
            $table->unsignedTinyInteger('answer_burden')->default(2);
            $table->boolean('critical')->default(false);
            $table->json('show_when')->nullable();
            $table->json('follow_up_rules')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['blueprint_module_id', 'question_version_id'], 'module_question_unique');
        });

        Schema::create('question_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('question_version_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('operator');
            $table->json('conditions');
            $table->json('effect')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('consultation_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('guest_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('blueprint_version_id')->constrained('consultation_blueprint_versions');
            $table->string('status')->default('active');
            $table->string('depth')->default('standard');
            $table->string('actual_stage')->nullable();
            $table->foreignId('current_question_version_id')->nullable()->constrained('question_versions')->nullOnDelete();
            $table->unsignedSmallInteger('questions_answered')->default(0);
            $table->unsignedTinyInteger('confidence')->default(0);
            $table->json('scope_snapshot')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'status']);
            $table->index(['status', 'last_activity_at']);
        });

        Schema::create('consultation_module_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('consultation_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('diagnostic_module_id')->constrained()->cascadeOnDelete();
            $table->string('state')->default('supporting');
            $table->text('reason')->nullable();
            $table->unsignedTinyInteger('completeness')->default(0);
            $table->unsignedTinyInteger('confidence')->default(0);
            $table->string('stop_reason')->nullable();
            $table->timestamps();
            $table->unique(['consultation_session_id', 'diagnostic_module_id'], 'session_module_unique');
        });

        Schema::create('consultation_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('consultation_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_version_id')->constrained();
            $table->json('value_json')->nullable();
            $table->string('source')->default('user');
            $table->string('confidence')->default('medium');
            $table->string('period')->nullable();
            $table->boolean('is_unknown')->default(false);
            $table->boolean('is_skipped')->default(false);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->unique(['consultation_session_id', 'question_version_id'], 'session_question_answer_unique');
        });

        Schema::create('consultation_evidence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('consultation_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consultation_answer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('source_label')->nullable();
            $table->text('source_locator')->nullable();
            $table->string('confidence')->default('low');
            $table->json('metadata')->nullable();
            $table->timestamp('observed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('consultation_conflicts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('consultation_session_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('severity')->default('medium');
            $table->text('message');
            $table->json('subject')->nullable();
            $table->string('status')->default('open');
            $table->json('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['consultation_session_id', 'key']);
        });

        Schema::create('consultation_inferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('consultation_session_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('type');
            $table->text('statement');
            $table->json('evidence_ids')->nullable();
            $table->json('opposing_evidence_ids')->nullable();
            $table->unsignedTinyInteger('confidence')->default(0);
            $table->string('status')->default('provisional');
            $table->timestamps();
            $table->unique(['consultation_session_id', 'key']);
        });

        Schema::create('consultation_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('consultation_session_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['name', 'occurred_at']);
        });

        Schema::table('agency_reports', function (Blueprint $table): void {
            $table->foreignId('consultation_session_id')->nullable()->after('project_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('agency_reports', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('consultation_session_id');
        });
        Schema::dropIfExists('consultation_events');
        Schema::dropIfExists('consultation_inferences');
        Schema::dropIfExists('consultation_conflicts');
        Schema::dropIfExists('consultation_evidence');
        Schema::dropIfExists('consultation_answers');
        Schema::dropIfExists('consultation_module_states');
        Schema::dropIfExists('consultation_sessions');
        Schema::dropIfExists('question_rules');
        Schema::dropIfExists('module_questions');
        Schema::dropIfExists('question_versions');
        Schema::dropIfExists('question_definitions');
        Schema::dropIfExists('blueprint_modules');
        Schema::dropIfExists('diagnostic_modules');
        Schema::table('consultation_blueprints', function (Blueprint $table): void {
            $table->dropForeign(['current_version_id']);
        });
        Schema::dropIfExists('consultation_blueprint_versions');
        Schema::dropIfExists('consultation_blueprints');
    }
};
