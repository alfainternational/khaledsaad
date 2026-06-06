<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->string('featured_image_alt')->nullable()->after('featured_image');
            $table->string('og_image')->nullable()->after('featured_image_alt');
            $table->string('category')->nullable()->after('og_image');
            $table->json('tags')->nullable()->after('category');
            $table->unsignedSmallInteger('reading_time_minutes')->nullable()->after('tags');
            $table->string('author_name')->nullable()->after('reading_time_minutes');
            $table->string('author_title')->nullable()->after('author_name');
            $table->boolean('is_featured')->default(false)->after('is_published');
            $table->unsignedInteger('sort_order')->default(0)->after('is_featured');
            $table->unsignedBigInteger('view_count')->default(0)->after('sort_order');

            $table->index('category');
            $table->index('is_featured');
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->dropIndex(['category']);
            $table->dropIndex(['is_featured']);
            $table->dropColumn([
                'featured_image_alt',
                'og_image',
                'category',
                'tags',
                'reading_time_minutes',
                'author_name',
                'author_title',
                'is_featured',
                'sort_order',
                'view_count',
            ]);
        });
    }
};
