<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnosis_cases', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('input_url')->nullable();
            $table->string('business_name')->nullable();
            $table->string('case_type', 40)->default('website'); // website|social|project|competitors
            $table->string('goal', 60)->nullable();
            $table->json('competitors_json')->nullable();
            $table->string('sector', 80)->default('general');
            $table->string('email')->nullable();
            $table->timestamp('email_captured_at')->nullable();
            $table->string('status', 20)->default('queued'); // queued|analyzing|ready|failed|converted|expired
            $table->unsignedTinyInteger('executive_score')->nullable();
            $table->string('integrity_status', 20)->nullable(); // verified|partial|insufficient
            $table->json('report_json')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('converted_workspace_id')->nullable();
            $table->unsignedBigInteger('converted_project_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('email');
            $table->index('status');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnosis_cases');
    }
};
