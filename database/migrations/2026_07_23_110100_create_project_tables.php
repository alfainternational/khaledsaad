<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('industry')->nullable();
            $table->string('stage')->default('growth');
            $table->string('status')->default('active');
            $table->unsignedTinyInteger('latest_score')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'slug']);
        });

        // ملف المشروع هو الذاكرة المشتركة: كل أداة تقرأ منه بدل إعادة السؤال.
        Schema::create('project_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('business_model')->nullable();
            $table->text('description')->nullable();
            $table->string('geography')->nullable();
            $table->string('website')->nullable();
            $table->unsignedInteger('monthly_budget')->nullable();
            $table->string('primary_goal')->nullable();
            $table->text('value_proposition')->nullable();
            $table->json('channels')->nullable();
            $table->json('extras')->nullable();
            $table->timestamps();
        });

        Schema::create('project_audiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('pains')->nullable();
            $table->text('gains')->nullable();
            $table->text('behaviors')->nullable();
            $table->timestamps();
        });

        Schema::create('project_competitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url')->nullable();
            $table->text('strengths')->nullable();
            $table->text('weaknesses')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_competitors');
        Schema::dropIfExists('project_audiences');
        Schema::dropIfExists('project_profiles');
        Schema::dropIfExists('projects');
    }
};
