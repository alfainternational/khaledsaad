<?php

namespace Tests\Feature;

use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use App\Services\Projects\ProjectService;
use App\Services\Tools\AttachmentExtractor;
use App\Services\Tools\ToolRunService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    #[Test]
    public function a_user_can_upload_an_evidence_file_to_their_run(): void
    {
        Storage::fake('local');

        [$user, $run] = $this->draftRun();

        $this->actingAs($user)
            ->post(route('app.runs.files.store', $run->uuid), [
                'file' => UploadedFile::fake()->createWithContent('notes.txt', 'ميزانيتنا 5000 ريال شهريًا'),
            ])
            ->assertRedirect();

        $this->assertSame(1, $run->files()->count());
        Storage::disk('local')->assertExists($run->files()->first()->path);
    }

    #[Test]
    public function a_plain_text_file_is_extracted_for_analysis(): void
    {
        Storage::fake('local');

        [$user, $run] = $this->draftRun();

        $this->actingAs($user)->post(route('app.runs.files.store', $run->uuid), [
            'file' => UploadedFile::fake()->createWithContent('brief.txt', 'نبيع خدمات استشارية للشركات الصغيرة'),
        ]);

        app(AttachmentExtractor::class)->extractAll($run->refresh());

        $file = $run->files()->first();
        $this->assertSame('completed', $file->extraction_status);
        $this->assertStringContainsString('استشارية', $file->extracted_text);
    }

    #[Test]
    public function an_unsupported_type_is_marked_clearly_rather_than_faked(): void
    {
        Storage::fake('local');

        [$user, $run] = $this->draftRun();

        // نوع غير مدعوم يُرفض عند الرفع أصلًا.
        $this->actingAs($user)
            ->post(route('app.runs.files.store', $run->uuid), [
                'file' => UploadedFile::fake()->create('archive.zip', 20, 'application/zip'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, $run->files()->count());
    }

    #[Test]
    public function a_user_can_delete_their_uploaded_file(): void
    {
        Storage::fake('local');

        [$user, $run] = $this->draftRun();
        $this->actingAs($user)->post(route('app.runs.files.store', $run->uuid), [
            'file' => UploadedFile::fake()->createWithContent('x.txt', 'محتوى'),
        ]);

        $file = $run->files()->first();

        $this->actingAs($user)
            ->delete(route('app.runs.files.destroy', [$run->uuid, $file->id]))
            ->assertRedirect();

        $this->assertSame(0, $run->fresh()->files()->count());
    }

    /**
     * @return array{0: User, 1: ToolRun}
     */
    private function draftRun(): array
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع المرفق']);
        $tool = Tool::where('key', 'marketing-score')->firstOrFail();
        $run = app(ToolRunService::class)->start($project, $tool, $user);

        return [$user, $run];
    }
}
