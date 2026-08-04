<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contents', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 24)->default('article')->index();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->json('body_json')->nullable();
            $table->longText('body_html')->nullable();
            $table->string('cover_image_path')->nullable();
            $table->text('video_url')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->string('access_level', 24)->default('public')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index(['type', 'status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contents');
    }
};
