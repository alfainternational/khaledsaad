<?php

namespace Tests\Feature;

use App\Models\Content;
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
}
