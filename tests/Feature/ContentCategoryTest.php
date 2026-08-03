<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\ContentCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_can_belong_to_an_ordered_active_category(): void
    {
        $later = ContentCategory::query()->create([
            'name' => 'المبيعات',
            'slug' => 'sales',
            'sort_order' => 20,
        ]);
        $first = ContentCategory::query()->create([
            'name' => 'التسويق',
            'slug' => 'marketing',
            'icon' => 'megaphone',
            'color' => '#2575ff',
            'sort_order' => 10,
        ]);
        ContentCategory::query()->create([
            'name' => 'قسم معطل',
            'slug' => 'disabled',
            'is_active' => false,
            'sort_order' => 0,
        ]);

        $content = Content::query()->create([
            'title' => 'درس التسويق',
            'slug' => 'marketing-lesson',
            'type' => Content::TYPE_LESSON,
            'category_id' => $first->id,
        ]);

        $this->assertTrue($content->category->is($first));
        $this->assertTrue($first->contents->contains($content));
        $this->assertSame(
            [$first->id, $later->id],
            ContentCategory::query()->active()->ordered()->pluck('id')->all(),
        );
    }

    public function test_existing_content_remains_valid_without_a_category(): void
    {
        $content = Content::query()->create([
            'title' => 'مقال قديم',
            'slug' => 'legacy-article',
        ]);

        $this->assertNull($content->category_id);
        $this->assertNull($content->category);
    }
}
