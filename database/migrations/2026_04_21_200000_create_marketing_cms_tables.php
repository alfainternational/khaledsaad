<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->longText('body_html')->nullable();
            $table->string('meta_description')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('blog_posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('body_html');
            $table->string('featured_image')->nullable();
            $table->string('meta_description')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->index(['is_published', 'published_at']);
        });

        Schema::create('case_studies', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('client_name');
            $table->string('industry')->nullable();
            $table->text('summary');
            $table->longText('body_html');
            $table->string('cover_image')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['is_published', 'published_at']);
        });

        Schema::create('community_posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('body_html');
            $table->string('author_display_name')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->index(['is_published', 'published_at']);
        });

        Schema::create('marketing_template_highlights', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->longText('body_html')->nullable();
            $table->string('category')->nullable();
            $table->string('icon_emoji')->nullable();
            $table->string('cta_label')->nullable();
            $table->string('cta_url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('partners', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('logo_path')->nullable();
            $table->text('description')->nullable();
            $table->string('website_url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('contact_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('subject');
            $table->text('body');
            $table->string('status')->default('new'); // new, read, archived
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('partners');
        Schema::dropIfExists('marketing_template_highlights');
        Schema::dropIfExists('community_posts');
        Schema::dropIfExists('case_studies');
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('cms_pages');
    }
};
