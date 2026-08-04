<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\ContentMedia;
use App\Models\ContentResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminContentMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_runtime_is_configured_for_256_megabyte_media_uploads(): void
    {
        $configuration = file_get_contents(public_path('.user.ini'));

        $this->assertStringContainsString('upload_max_filesize=256M', $configuration);
        $this->assertStringContainsString('post_max_size=272M', $configuration);
    }

    public function test_admin_uploads_a_safe_image_to_the_local_public_library(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->postJson(route('admin.content.media.store'), [
            'file' => UploadedFile::fake()->image('cover.jpg', 1200, 630),
            'alt_text' => 'صورة توضيحية',
        ]);

        $media = ContentMedia::query()->sole();

        $response->assertCreated()
            ->assertJsonPath('data.id', $media->id)
            ->assertJsonPath('data.alt_text', 'صورة توضيحية');

        Storage::disk('local')->assertExists($media->path);
        $this->assertSame($admin->id, $media->uploaded_by);
        $this->assertStringStartsWith('content/', $media->path);
        $this->assertNotSame('cover.jpg', basename($media->path));
    }

    public function test_images_with_unusual_dimensions_are_accepted_up_to_256_megabytes(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->postJson(route('admin.content.media.store'), [
            'file' => UploadedFile::fake()->image('portrait.jpg', 333, 1777),
        ])->assertCreated();

        $this->actingAs($admin)->postJson(route('admin.content.media.store'), [
            'file' => UploadedFile::fake()->create('too-large.jpg', 256 * 1024 + 1, 'image/jpeg'),
        ])->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'الحد الأقصى لحجم الصورة أو المرفق هو 256 ميجابايت.');
    }

    public function test_media_size_is_formatted_for_the_arabic_interface(): void
    {
        $media = new ContentMedia(['size_bytes' => 1572864]);

        $this->assertSame('1.5 ميجابايت', $media->humanReadableSize());
    }

    public function test_svg_and_non_admin_uploads_are_rejected(): void
    {
        Storage::fake('local');

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.content.media.store'), [
                'file' => UploadedFile::fake()->create('bad.svg', 10, 'image/svg+xml'),
            ])
            ->assertForbidden();

        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->postJson(route('admin.content.media.store'), [
                'file' => UploadedFile::fake()->create('bad.svg', 10, 'image/svg+xml'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('content_media', 0);
    }

    public function test_admin_can_browse_and_delete_media_from_the_library(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['is_admin' => true]);
        Storage::disk('local')->put('content/test/image.jpg', 'image');
        $media = ContentMedia::query()->create([
            'disk' => 'local',
            'path' => 'content/test/image.jpg',
            'original_name' => 'original.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 5,
            'alt_text' => 'غلاف المقال',
            'uploaded_by' => $admin->id,
        ]);

        $this->actingAs($admin)->get('/admin/content-media')
            ->assertOk()
            ->assertSee('مكتبة الوسائط')
            ->assertSee('original.jpg');

        $this->actingAs($admin)->delete('/admin/content-media/'.$media->id)
            ->assertRedirect();

        Storage::disk('local')->assertMissing('content/test/image.jpg');
        $this->assertDatabaseMissing('content_media', ['id' => $media->id]);
    }

    public function test_admin_uploads_office_and_archive_materials(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['is_admin' => true]);

        foreach ([
            ['worksheet.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            ['materials.zip', 'application/zip'],
        ] as [$name, $mime]) {
            $this->actingAs($admin)->postJson(route('admin.content.media.store'), [
                'file' => UploadedFile::fake()->create($name, 24, $mime),
            ])->assertCreated();
        }

        $this->assertDatabaseCount('content_media', 2);
    }

    public function test_media_attached_to_content_cannot_be_deleted(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['is_admin' => true]);
        $content = Content::query()->create([
            'title' => 'درس',
            'slug' => 'protected-media',
            'created_by' => $admin->id,
        ]);
        Storage::disk('local')->put('content/test/guide.pdf', 'pdf');
        $media = ContentMedia::query()->create([
            'disk' => 'local',
            'path' => 'content/test/guide.pdf',
            'original_name' => 'guide.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 3,
            'uploaded_by' => $admin->id,
        ]);
        ContentResource::query()->create([
            'content_id' => $content->id,
            'type' => ContentResource::TYPE_FILE,
            'title' => 'الدليل',
            'content_media_id' => $media->id,
        ]);

        $this->actingAs($admin)->delete(route('admin.content-media.destroy', $media))
            ->assertRedirect()
            ->assertSessionHasErrors('media');

        Storage::disk('local')->assertExists($media->path);
        $this->assertDatabaseHas('content_media', ['id' => $media->id]);
    }
}
