<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tools', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('title');
            $table->text('description');
            $table->string('category');
            // published = قابلة للتشغيل | coming_soon = معروضة بوضوح وغير قابلة للتشغيل
            $table->string('status')->default('coming_soon');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tool_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tool_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version')->default(1);
            $table->unsignedSmallInteger('credit_cost')->default(1);
            $table->string('status')->default('draft');
            $table->json('output_schema');
            $table->json('scoring_rules')->nullable();
            $table->json('section_plan');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['tool_id', 'version']);
        });

        Schema::table('tools', function (Blueprint $table) {
            $table->foreignId('current_version_id')->nullable()->after('status')
                ->constrained('tool_versions')->nullOnDelete();
        });

        // الحقول تُبنى من قاعدة البيانات، فتتغير أسئلة الأداة من لوحة الإدارة دون نشر كود.
        Schema::create('tool_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tool_version_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->string('help')->nullable();
            $table->string('type');
            $table->json('options')->nullable();
            $table->string('validation')->nullable();
            $table->boolean('required')->default(true);
            $table->unsignedSmallInteger('step')->default(1);
            $table->string('step_title')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('visible_when')->nullable();
            $table->string('profile_key')->nullable();
            $table->timestamps();

            $table->unique(['tool_version_id', 'key']);
        });

        // BR-012: إصدار البرومبت غير قابل للتعديل بعد استخدامه.
        Schema::create('prompt_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tool_version_id')->constrained()->cascadeOnDelete();
            $table->string('stage');
            $table->string('tier')->default('economy');
            $table->longText('content');
            $table->string('status')->default('published');
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->unique(['tool_version_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompt_versions');
        Schema::dropIfExists('tool_fields');

        Schema::table('tools', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_version_id');
        });

        Schema::dropIfExists('tool_versions');
        Schema::dropIfExists('tools');
    }
};
