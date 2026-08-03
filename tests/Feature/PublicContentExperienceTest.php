<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\ContentCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicContentExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_library_supports_search_type_tabs_and_topic_categories(): void
    {
        $marketing = ContentCategory::query()->create([
            'name' => 'التسويق',
            'slug' => 'marketing',
            'icon' => 'megaphone',
        ]);
        $sales = ContentCategory::query()->create([
            'name' => 'المبيعات',
            'slug' => 'sales',
            'icon' => 'chart',
        ]);
        $this->published('خطة المحتوى', 'content-plan', Content::TYPE_ARTICLE, $marketing);
        $this->published('درس فهم السوق', 'market-lesson', Content::TYPE_LESSON, $marketing);
        $this->published('محاضرة الإغلاق', 'closing-lecture', Content::TYPE_LECTURE, $sales);

        $this->get(route('content.index', ['category' => 'marketing']))
            ->assertOk()
            ->assertSee('data-content-type-tabs', false)
            ->assertSee('data-content-category-nav', false)
            ->assertSee('content-library-featured', false)
            ->assertSee('التسويق')
            ->assertSee('خطة المحتوى')
            ->assertSee('درس فهم السوق')
            ->assertDontSee('محاضرة الإغلاق');

        $this->get(route('content.index', ['q' => 'السوق']))
            ->assertOk()
            ->assertSee('درس فهم السوق')
            ->assertDontSee('خطة المحتوى')
            ->assertDontSee('محاضرة الإغلاق');

        $this->get(route('content.index', ['type' => Content::TYPE_LECTURE]))
            ->assertOk()
            ->assertSee('محاضرة الإغلاق')
            ->assertDontSee('درس فهم السوق');
    }

    public function test_reading_page_shows_compact_metadata_category_and_related_materials(): void
    {
        $marketing = ContentCategory::query()->create([
            'name' => 'التسويق',
            'slug' => 'marketing',
        ]);
        $article = $this->published('المقال الأساسي', 'main-article', Content::TYPE_ARTICLE, $marketing);
        $article->update(['cover_image_path' => 'content/covers/main-article.webp']);
        $related = $this->published('مادة مرتبطة', 'related-item', Content::TYPE_LESSON, $marketing);
        $unrelatedCategory = ContentCategory::query()->create(['name' => 'الإدارة', 'slug' => 'management']);
        $this->published('مادة بعيدة', 'unrelated-item', Content::TYPE_ARTICLE, $unrelatedCategory);

        $this->get(route('content.show', $article))
            ->assertOk()
            ->assertSee('content-reading-grid', false)
            ->assertSee('/storage/content/covers/main-article.webp', false)
            ->assertSee('تفاصيل المادة')
            ->assertSee('التسويق')
            ->assertSee('قد يعجبك أيضًا')
            ->assertSee($related->title)
            ->assertDontSee('مادة بعيدة');
    }

    private function published(
        string $title,
        string $slug,
        string $type,
        ContentCategory $category,
    ): Content {
        return Content::query()->create([
            'title' => $title,
            'slug' => $slug,
            'type' => $type,
            'category_id' => $category->id,
            'excerpt' => 'ملخص عملي للمادة يساعد القارئ على اتخاذ الخطوة التالية.',
            'body_html' => '<h2>عنوان داخلي</h2><p>محتوى المادة الكامل.</p>',
            'duration_minutes' => 12,
            'status' => Content::STATUS_PUBLISHED,
            'published_at' => now()->subMinute(),
        ]);
    }
}
