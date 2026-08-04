<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\ContentMedia;
use App\Models\ContentResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_has_ordered_file_and_link_resources(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $content = Content::query()->create([
            'title' => 'درس الموارد',
            'slug' => 'resources-lesson',
            'type' => Content::TYPE_LESSON,
            'created_by' => $admin->id,
        ]);
        $media = ContentMedia::query()->create([
            'disk' => 'local',
            'path' => 'content/test/guide.pdf',
            'original_name' => 'guide.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'uploaded_by' => $admin->id,
        ]);

        ContentResource::query()->create([
            'content_id' => $content->id,
            'type' => ContentResource::TYPE_LINK,
            'title' => 'رابط إضافي',
            'url' => 'https://example.com/reference',
            'position' => 2,
        ]);
        ContentResource::query()->create([
            'content_id' => $content->id,
            'type' => ContentResource::TYPE_FILE,
            'title' => 'ملف الدرس',
            'content_media_id' => $media->id,
            'position' => 1,
        ]);

        $this->assertSame(['ملف الدرس', 'رابط إضافي'], $content->resources->pluck('title')->all());
        $this->assertSame($media->id, $content->resources->first()->media->id);
    }
}
