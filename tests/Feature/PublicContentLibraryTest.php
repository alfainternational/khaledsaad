<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\ContentMedia;
use App\Models\ContentResource;
use App\Models\CourseSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicContentLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_library_lists_only_published_content_and_filters_by_type(): void
    {
        $author = User::factory()->create();
        $article = $this->content($author, 'مقال منشور', 'article', Content::TYPE_ARTICLE);
        $article->update(['cover_image_path' => 'content/covers/article.jpg']);
        $this->content($author, 'دورة منشورة', 'course', Content::TYPE_COURSE);
        Content::query()->create([
            'title' => 'مسودة مخفية',
            'slug' => 'draft',
            'created_by' => $author->id,
        ]);

        $this->get(route('content.index'))
            ->assertOk()
            ->assertSee('مقال منشور')
            ->assertSee('دورة منشورة')
            ->assertSee('/storage/content/covers/article.jpg', false)
            ->assertDontSee('مسودة مخفية');

        $this->get(route('content.index', ['type' => Content::TYPE_COURSE]))
            ->assertOk()
            ->assertSee('دورة منشورة')
            ->assertDontSee('مقال منشور');
    }

    public function test_free_content_is_readable_and_drafts_return_not_found(): void
    {
        $author = User::factory()->create();
        $published = $this->content($author, 'مقال مجاني', 'free-article', Content::TYPE_ARTICLE, '<p>النص الكامل</p>');
        $published->update(['cover_image_path' => 'content/covers/free-article.jpg']);
        $draft = Content::query()->create([
            'title' => 'مسودة',
            'slug' => 'hidden-draft',
            'created_by' => $author->id,
        ]);

        $this->get(route('content.show', $published))
            ->assertOk()
            ->assertSee('النص الكامل')
            ->assertSee('/storage/content/covers/free-article.jpg', false);

        $this->get(route('content.show', $draft))->assertNotFound();
    }

    public function test_subscriber_content_hides_the_body_until_email_consent(): void
    {
        $author = User::factory()->create();
        $content = $this->content(
            $author,
            'درس للمشتركين',
            'subscriber-lesson',
            Content::TYPE_LESSON,
            '<p>سر الدرس الكامل</p>',
            Content::ACCESS_SUBSCRIBERS,
        );

        $this->get(route('content.show', $content))
            ->assertOk()
            ->assertSee('سجّل بريدك لفتح المحتوى')
            ->assertDontSee('سر الدرس الكامل');

        $this->from(route('content.show', $content))
            ->post(route('content.subscribe', $content), [
                'email' => 'reader@example.com',
                'consent' => '1',
            ])
            ->assertRedirect(route('content.show', $content));

        $this->get(route('content.show', $content))
            ->assertOk()
            ->assertSee('سر الدرس الكامل');

        $this->assertDatabaseHas('content_subscribers', ['email' => 'reader@example.com']);
    }

    public function test_course_page_shows_published_curriculum_items(): void
    {
        $author = User::factory()->create();
        $course = $this->content($author, 'الدورة الكاملة', 'full-course', Content::TYPE_COURSE);
        $lesson = $this->content($author, 'الدرس الأول', 'course-lesson', Content::TYPE_LESSON);
        $section = CourseSection::query()->create([
            'course_id' => $course->id,
            'title' => 'البداية',
            'position' => 1,
        ]);
        $section->items()->attach($lesson->id, ['position' => 1]);

        $this->get(route('content.show', $course))
            ->assertOk()
            ->assertSee('البداية')
            ->assertSee('الدرس الأول');
    }

    public function test_private_media_requires_the_same_email_gate_as_its_content(): void
    {
        Storage::fake('local');
        $author = User::factory()->create();
        Storage::disk('local')->put('content/private/lesson.pdf', 'private-file');
        $media = ContentMedia::query()->create([
            'disk' => 'local',
            'path' => 'content/private/lesson.pdf',
            'original_name' => 'lesson.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 12,
            'uploaded_by' => $author->id,
        ]);
        $content = $this->content(
            $author,
            'ملف خاص',
            'private-file',
            Content::TYPE_LESSON,
            '<p><a href="'.$media->url().'">تحميل</a></p>',
            Content::ACCESS_SUBSCRIBERS,
        );

        Storage::disk('local')->put('content/public/lesson-10.pdf', 'public-file');
        $publicMedia = ContentMedia::query()->forceCreate([
            'id' => 10,
            'disk' => 'local',
            'path' => 'content/public/lesson-10.pdf',
            'original_name' => 'lesson-10.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 11,
            'uploaded_by' => $author->id,
        ]);
        $this->content(
            $author,
            'ملف عام آخر',
            'public-file-10',
            Content::TYPE_LESSON,
            '<p><a href="'.$publicMedia->url().'">تحميل</a></p>',
        );

        $this->get($media->url())->assertNotFound();

        $this->post(route('content.subscribe', $content), [
            'email' => 'media-reader@example.com',
            'consent' => '1',
        ])->assertRedirect();

        $response = $this->get($media->url());
        $response->assertOk();
        $this->assertStringContainsString('private-file', $response->streamedContent());
    }

    public function test_unlocked_content_shows_downloadable_files_and_external_links(): void
    {
        Storage::fake('local');
        $author = User::factory()->create();
        $content = $this->content($author, 'درس الموارد', 'public-resources', Content::TYPE_LESSON);
        Storage::disk('local')->put('content/resources/worksheet.pdf', 'worksheet-content');
        $media = ContentMedia::query()->create([
            'disk' => 'local',
            'path' => 'content/resources/worksheet.pdf',
            'original_name' => 'worksheet.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 17,
            'uploaded_by' => $author->id,
        ]);
        $file = $content->resources()->create([
            'type' => ContentResource::TYPE_FILE,
            'title' => 'ورقة العمل',
            'content_media_id' => $media->id,
            'position' => 0,
        ]);
        $content->resources()->create([
            'type' => ContentResource::TYPE_LINK,
            'title' => 'المرجع الخارجي',
            'url' => 'https://example.com/reference',
            'position' => 1,
        ]);

        $this->get(route('content.show', $content))
            ->assertOk()
            ->assertSee('المواد المصاحبة')
            ->assertSee('ورقة العمل')
            ->assertSee('المرجع الخارجي')
            ->assertSee('https://example.com/reference', false);

        $download = $this->get(route('content.resources.download', [$content, $file]));
        $download->assertOk()->assertDownload('worksheet.pdf');
        $this->assertStringContainsString('worksheet-content', $download->streamedContent());
    }

    public function test_subscriber_resources_stay_hidden_and_protected_until_email_unlock(): void
    {
        Storage::fake('local');
        $author = User::factory()->create();
        $content = $this->content(
            $author,
            'درس خاص بمواد',
            'private-resources',
            Content::TYPE_LESSON,
            '<p>المحتوى الخاص</p>',
            Content::ACCESS_SUBSCRIBERS,
        );
        Storage::disk('local')->put('content/resources/private.pdf', 'private-resource');
        $media = ContentMedia::query()->create([
            'disk' => 'local',
            'path' => 'content/resources/private.pdf',
            'original_name' => 'private.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 16,
            'uploaded_by' => $author->id,
        ]);
        $resource = $content->resources()->create([
            'type' => ContentResource::TYPE_FILE,
            'title' => 'المادة السرية',
            'content_media_id' => $media->id,
        ]);

        $this->get(route('content.show', $content))->assertDontSee('المادة السرية');
        $this->get(route('content.resources.download', [$content, $resource]))->assertNotFound();

        $this->post(route('content.subscribe', $content), [
            'email' => 'resource-reader@example.com',
            'consent' => '1',
        ])->assertRedirect();

        $this->get(route('content.show', $content))->assertSee('المادة السرية');
        $this->get(route('content.resources.download', [$content, $resource]))->assertOk();
    }

    private function content(
        User $author,
        string $title,
        string $slug,
        string $type,
        string $body = '<p>محتوى</p>',
        string $access = Content::ACCESS_PUBLIC,
    ): Content {
        return Content::query()->create([
            'title' => $title,
            'slug' => $slug,
            'type' => $type,
            'excerpt' => 'ملخص واضح',
            'body_html' => $body,
            'status' => Content::STATUS_PUBLISHED,
            'access_level' => $access,
            'published_at' => now()->subMinute(),
            'created_by' => $author->id,
        ]);
    }
}
