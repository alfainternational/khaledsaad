<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_content_defaults_to_a_free_article_draft(): void
    {
        $content = Content::query()->create([
            'title' => 'مقال مجاني',
            'slug' => 'internal-article',
            'excerpt' => 'ملخص المقال',
            'created_by' => User::factory()->create()->id,
        ]);

        $this->assertSame(Content::TYPE_ARTICLE, $content->type);
        $this->assertSame(Content::STATUS_DRAFT, $content->status);
        $this->assertSame(Content::ACCESS_PUBLIC, $content->access_level);
        $this->assertSame('internal-article', $content->getRouteKey());
    }

    public function test_published_scope_excludes_future_drafts_and_archived_content(): void
    {
        $author = User::factory()->create();

        $published = Content::query()->create([
            'title' => 'مجدول',
            'slug' => 'published',
            'status' => Content::STATUS_PUBLISHED,
            'published_at' => now()->subMinute(),
            'created_by' => $author->id,
        ]);

        foreach ([
            ['slug' => 'draft', 'status' => Content::STATUS_DRAFT, 'published_at' => null],
            ['slug' => 'future', 'status' => Content::STATUS_SCHEDULED, 'published_at' => now()->addDay()],
            ['slug' => 'archived', 'status' => Content::STATUS_ARCHIVED, 'published_at' => now()->subDay()],
        ] as $item) {
            Content::query()->create([
                'title' => $item['slug'],
                'slug' => $item['slug'],
                'status' => $item['status'],
                'published_at' => $item['published_at'],
                'created_by' => $author->id,
            ]);
        }

        $this->assertSame([$published->id], Content::query()->published()->pluck('id')->all());
        $this->assertTrue($published->isPublished());
    }
}
