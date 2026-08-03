<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\ContentMedia;
use App\Models\ContentResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminContentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_open_content_management(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.content.index'))
            ->assertNotFound();

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('admin.content.index'))
            ->assertOk()
            ->assertSee('إدارة المحتوى');
    }

    public function test_content_editor_uses_the_available_panel_width(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.content.create'))
            ->assertOk()
            ->assertSee('content-form--fluid', false);
    }

    public function test_content_editor_exposes_grouped_icon_toolbar_and_main_image_uploader(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.content.create'))
            ->assertOk()
            ->assertSee('data-editor-toolbar', false)
            ->assertSee('aria-label="أدوات تحرير المحتوى"', false)
            ->assertSee('data-content-cover', false)
            ->assertSee('data-cover-file', false)
            ->assertSee('data-cover-preview', false)
            ->assertSee('name="cover_image_path"', false)
            ->assertSee('الصورة الرئيسية');
    }

    public function test_admin_can_save_uploaded_main_image_url(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.content.store'), [
            'type' => Content::TYPE_ARTICLE,
            'title' => 'مقال بصورة',
            'slug' => 'article-with-cover',
            'cover_image_path' => '/blog/media/42',
            'status' => Content::STATUS_DRAFT,
        ])->assertRedirect();

        $this->assertDatabaseHas('contents', [
            'slug' => 'article-with-cover',
            'cover_image_path' => '/blog/media/42',
        ]);
    }

    public function test_content_editor_includes_uploadable_files_and_external_links_component(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $content = Content::query()->create([
            'title' => 'درس الموارد',
            'slug' => 'resource-editor',
            'type' => Content::TYPE_LESSON,
            'created_by' => $admin->id,
        ]);
        $content->resources()->create([
            'type' => ContentResource::TYPE_LINK,
            'title' => 'مرجع محفوظ',
            'url' => 'https://example.com/saved',
            'position' => 0,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.content.edit', $content))
            ->assertOk()
            ->assertSee('data-content-resources', false)
            ->assertSee('data-media-max-bytes="268435456"', false)
            ->assertSee('تُقبل كل الأبعاد')
            ->assertSee('256 ميجابايت')
            ->assertSee(route('admin.content.media.store'), false)
            ->assertSee('name="resources_json"', false)
            ->assertSee('مرجع محفوظ');
    }

    public function test_admin_creates_free_content_by_default_and_html_is_sanitized(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->post(route('admin.content.store'), [
            'type' => Content::TYPE_ARTICLE,
            'title' => 'مقال التجربة',
            'slug' => 'marketing-guide',
            'excerpt' => 'ملخص قصير',
            'body_html' => '<h2 onclick="bad()">العنوان</h2><script>alert(1)</script>',
            'body_json' => json_encode(['type' => 'doc', 'content' => []]),
            'status' => Content::STATUS_PUBLISHED,
            'published_at' => now()->subMinute()->format('Y-m-d H:i:s'),
        ]);

        $content = Content::query()->sole();

        $response->assertRedirect(route('admin.content.edit', $content));
        $this->assertSame(Content::ACCESS_PUBLIC, $content->access_level);
        $this->assertSame($admin->id, $content->created_by);
        $this->assertStringContainsString('<h2>العنوان</h2>', $content->body_html);
        $this->assertStringNotContainsString('script', $content->body_html);
        $this->assertSame('doc', $content->body_json['type']);
    }

    public function test_admin_can_gate_archive_and_restore_content(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $content = Content::query()->create([
            'title' => 'المسودة',
            'slug' => 'lecture',
            'type' => Content::TYPE_LECTURE,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->put(route('admin.content.update', $content), [
            'type' => Content::TYPE_LECTURE,
            'title' => 'المسودة المعدلة',
            'slug' => 'lecture',
            'body_html' => '<p>المحتوى</p>',
            'status' => Content::STATUS_DRAFT,
            'access_level' => Content::ACCESS_SUBSCRIBERS,
        ])->assertRedirect(route('admin.content.edit', $content));

        $this->assertSame(Content::ACCESS_SUBSCRIBERS, $content->fresh()->access_level);

        $this->actingAs($admin)->patch(route('admin.content.archive', $content))->assertRedirect();
        $this->assertSame(Content::STATUS_ARCHIVED, $content->fresh()->status);

        $this->actingAs($admin)->patch(route('admin.content.restore', $content))->assertRedirect();
        $this->assertSame(Content::STATUS_DRAFT, $content->fresh()->status);
    }

    public function test_slug_must_be_unique(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Content::query()->create(['title' => 'الأول', 'slug' => 'same', 'created_by' => $admin->id]);

        $this->actingAs($admin)->post(route('admin.content.store'), [
            'type' => Content::TYPE_ARTICLE,
            'title' => 'الثاني',
            'slug' => 'same',
            'status' => Content::STATUS_DRAFT,
        ])->assertSessionHasErrors('slug');

        $this->assertSame(1, Content::query()->count());
    }

    public function test_scheduled_content_requires_a_future_publish_time(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $payload = [
            'type' => Content::TYPE_ARTICLE,
            'title' => 'مقال مجدول',
            'slug' => 'scheduled-article',
            'status' => Content::STATUS_SCHEDULED,
        ];

        $this->actingAs($admin)->post(route('admin.content.store'), $payload)
            ->assertSessionHasErrors('published_at');

        $this->actingAs($admin)->post(route('admin.content.store'), $payload + [
            'published_at' => now()->subMinute()->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors('published_at');

        $this->actingAs($admin)->post(route('admin.content.store'), $payload + [
            'published_at' => now()->addHour()->format('Y-m-d H:i:s'),
        ])->assertRedirect();

        $this->assertDatabaseHas('contents', [
            'slug' => 'scheduled-article',
            'status' => Content::STATUS_SCHEDULED,
        ]);
    }

    public function test_admin_synchronizes_uploaded_files_and_links_with_content(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $media = ContentMedia::query()->create([
            'disk' => 'local',
            'path' => 'content/test/worksheet.pdf',
            'original_name' => 'worksheet.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 2048,
            'uploaded_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.content.store'), [
            'type' => Content::TYPE_LESSON,
            'title' => 'درس مع مواد',
            'slug' => 'lesson-with-resources',
            'status' => Content::STATUS_DRAFT,
            'resources_json' => json_encode([
                ['type' => 'file', 'title' => 'ورقة العمل', 'media_id' => $media->id],
                ['type' => 'link', 'title' => 'مرجع خارجي', 'url' => 'https://example.com/reference'],
            ]),
        ]);

        $content = Content::query()->sole();
        $response->assertRedirect(route('admin.content.edit', $content));
        $this->assertSame(['ورقة العمل', 'مرجع خارجي'], $content->resources->pluck('title')->all());
        $this->assertSame([0, 1], $content->resources->pluck('position')->all());
        $this->assertSame($media->id, $content->resources->first()->content_media_id);

        $this->actingAs($admin)->put(route('admin.content.update', $content), [
            'type' => Content::TYPE_LESSON,
            'title' => 'درس مع مواد',
            'slug' => 'lesson-with-resources',
            'status' => Content::STATUS_DRAFT,
            'resources_json' => json_encode([
                ['type' => 'link', 'title' => 'مرجع محدث', 'url' => 'https://example.org/updated'],
            ]),
        ])->assertRedirect(route('admin.content.edit', $content));

        $this->assertDatabaseCount('content_resources', 1);
        $this->assertDatabaseHas('content_resources', [
            'content_id' => $content->id,
            'type' => ContentResource::TYPE_LINK,
            'title' => 'مرجع محدث',
            'url' => 'https://example.org/updated',
            'position' => 0,
        ]);
    }

    public function test_content_resources_reject_forged_files_and_unsafe_links(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $base = [
            'type' => Content::TYPE_ARTICLE,
            'title' => 'محتوى غير صالح',
            'slug' => 'invalid-resources',
            'status' => Content::STATUS_DRAFT,
        ];

        $this->actingAs($admin)->post(route('admin.content.store'), $base + [
            'resources_json' => json_encode([
                ['type' => 'file', 'title' => 'ملف مزور', 'media_id' => 999999],
            ]),
        ])->assertSessionHasErrors('resources.0.media_id');

        $this->actingAs($admin)->post(route('admin.content.store'), $base + [
            'resources_json' => json_encode([
                ['type' => 'link', 'title' => 'رابط غير آمن', 'url' => 'javascript:alert(1)'],
            ]),
        ])->assertSessionHasErrors('resources.0.url');

        $this->assertDatabaseCount('contents', 0);
    }
}
