<?php

namespace Tests\Feature;

use App\Models\ContentMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminContentMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_uploads_a_safe_image_to_the_local_public_library(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->postJson(route('admin.content.media.store'), [
            'file' => UploadedFile::fake()->image('cover.jpg', 1200, 630),
            'alt_text' => '???? ??????',
        ]);

        $media = ContentMedia::query()->sole();

        $response->assertCreated()
            ->assertJsonPath('data.id', $media->id)
            ->assertJsonPath('data.alt_text', '???? ??????');

        Storage::disk('public')->assertExists($media->path);
        $this->assertSame($admin->id, $media->uploaded_by);
        $this->assertStringStartsWith('content/', $media->path);
        $this->assertNotSame('cover.jpg', basename($media->path));
    }

    public function test_svg_and_non_admin_uploads_are_rejected(): void
    {
        Storage::fake('public');

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
}
