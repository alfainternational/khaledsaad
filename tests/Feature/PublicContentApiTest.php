<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\ContentCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicContentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_lists_and_filters_only_published_content(): void
    {
        $author = User::factory()->create();
        $this->content($author, 'مقال منشور', 'published-article', Content::TYPE_ARTICLE);
        $this->content($author, 'دورة منشورة', 'published-course', Content::TYPE_COURSE);
        Content::query()->create([
            'title' => 'مسودة مخفية',
            'slug' => 'hidden-draft',
            'created_by' => $author->id,
        ]);

        $this->getJson('/api/v1/public/content?type=course')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'دورة منشورة')
            ->assertJsonPath('data.0.locked', false)
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_api_filters_by_category_and_exposes_category_metadata(): void
    {
        $author = User::factory()->create();
        $marketing = ContentCategory::query()->create([
            'name' => 'التسويق',
            'slug' => 'marketing',
            'icon' => 'megaphone',
            'color' => '#2575ff',
        ]);
        $sales = ContentCategory::query()->create(['name' => 'المبيعات', 'slug' => 'sales']);
        $marketingItem = $this->content($author, 'مادة التسويق', 'marketing-api-item', Content::TYPE_LESSON);
        $marketingItem->update(['category_id' => $marketing->id, 'cover_image_path' => '/blog/media/9']);
        $salesItem = $this->content($author, 'مادة المبيعات', 'sales-api-item', Content::TYPE_ARTICLE);
        $salesItem->update(['category_id' => $sales->id]);

        $this->getJson('/api/v1/public/content?category=marketing')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'مادة التسويق')
            ->assertJsonPath('data.0.cover_image_url', '/blog/media/9')
            ->assertJsonPath('data.0.category.name', 'التسويق')
            ->assertJsonPath('data.0.category.slug', 'marketing')
            ->assertJsonPath('data.0.category.icon', 'megaphone')
            ->assertJsonPath('data.0.category.color', '#2575ff');
    }

    public function test_api_redacts_gated_body_until_email_subscription_token_is_sent(): void
    {
        $author = User::factory()->create();
        $content = $this->content(
            $author,
            'درس خاص',
            'private-lesson',
            Content::TYPE_LESSON,
            Content::ACCESS_SUBSCRIBERS,
            '<p>المحتوى الكامل</p>',
        );

        $this->getJson('/api/v1/public/content/'.$content->slug)
            ->assertOk()
            ->assertJsonPath('data.locked', true)
            ->assertJsonPath('data.body_html', null)
            ->assertJsonMissing(['body_html' => '<p>المحتوى الكامل</p>']);

        $token = $this->postJson('/api/v1/public/content/subscribe', [
            'email' => 'Reader@Example.com',
            'consent' => true,
        ])->assertCreated()->json('data.access_token');

        $this->withHeader('X-Content-Token', $token)
            ->getJson('/api/v1/public/content/'.$content->slug)
            ->assertOk()
            ->assertJsonPath('data.locked', false)
            ->assertJsonPath('data.body_html', '<p>المحتوى الكامل</p>');
    }

    public function test_homepage_and_sitemap_use_internal_published_content(): void
    {
        $author = User::factory()->create();
        $content = $this->content($author, 'معرفة من المدونة', 'internal-knowledge', Content::TYPE_ARTICLE);

        $this->get('/')
            ->assertOk()
            ->assertSee('معرفة من المدونة')
            ->assertSee(route('content.show', $content))
            ->assertDontSee('اقرأ على LinkedIn');

        cache()->forget('sitemap.xml');
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee(route('content.index'), false)
            ->assertSee(route('content.show', $content), false);
    }

    private function content(
        User $author,
        string $title,
        string $slug,
        string $type,
        string $access = Content::ACCESS_PUBLIC,
        string $body = '<p>المحتوى</p>',
    ): Content {
        return Content::query()->create([
            'title' => $title,
            'slug' => $slug,
            'type' => $type,
            'status' => Content::STATUS_PUBLISHED,
            'access_level' => $access,
            'excerpt' => 'ملخص داخلي',
            'body_html' => $body,
            'published_at' => now()->subMinute(),
            'created_by' => $author->id,
        ]);
    }
}
