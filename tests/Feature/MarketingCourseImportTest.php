<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Services\Content\MarketingCourseImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class MarketingCourseImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_exposes_source_and_learning_metadata(): void
    {
        $content = Content::query()->create([
            'title' => 'درس تجريبي',
            'slug' => 'test-learning-metadata',
            'source_key' => 'marketing-course-01',
            'source_filename' => '1 المقدمة والدرس الأول.docx',
            'source_text_hash' => hash('sha256', 'نص'),
            'learning_order' => 1,
            'learning_meta' => [
                'outline' => [
                    ['id' => 'start', 'title' => 'البداية'],
                ],
            ],
        ]);

        $content->refresh();

        $this->assertSame(1, $content->learning_order);
        $this->assertSame('البداية', $content->learning_meta['outline'][0]['title']);
        $this->assertSame('marketing-course-01', $content->source_key);
    }

    public function test_import_updates_existing_first_article_and_is_idempotent(): void
    {
        $existing = Content::query()->create([
            'title' => 'المقال القديم',
            'slug' => '1',
            'body_html' => '<p>نسخة قديمة</p>',
        ]);

        $this->artisan('content:import-marketing-course', ['--publish' => true])
            ->expectsOutputToContain('20')
            ->assertSuccessful();

        $first = Content::query()->where('source_key', 'marketing-course-01')->sole();

        $this->assertSame($existing->id, $first->id);
        $this->assertSame('1', $first->slug);
        $this->assertSame(Content::STATUS_PUBLISHED, $first->status);
        $this->assertNotSame('<p>نسخة قديمة</p>', $first->body_html);
        $this->assertDatabaseCount('contents', 20);
        $this->assertDatabaseHas('contents', [
            'source_key' => 'marketing-course-20',
            'learning_order' => 20,
            'slug' => 'ورشة-عمل-تسويقية-تطبيقية',
        ]);

        $this->artisan('content:import-marketing-course', ['--publish' => true])->assertSuccessful();

        $this->assertDatabaseCount('contents', 20);
        $this->assertSame($existing->id, Content::query()->where('source_key', 'marketing-course-01')->value('id'));
    }

    public function test_import_refuses_to_overwrite_an_unrelated_later_slug(): void
    {
        $unrelated = Content::query()->create([
            'title' => 'محتوى مستقل',
            'slug' => 'افهم-السوق-قبل-أن-تبدأ',
            'body_html' => '<p>لا تعدله</p>',
        ]);

        try {
            app(MarketingCourseImporter::class)->import();
            $this->fail('The importer should reject a slug owned by unrelated content.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('افهم-السوق-قبل-أن-تبدأ', $exception->getMessage());
        }

        $this->assertSame('محتوى مستقل', $unrelated->fresh()->title);
        $this->assertSame('<p>لا تعدله</p>', $unrelated->fresh()->body_html);
        $this->assertNull($unrelated->fresh()->source_key);
        $this->assertDatabaseCount('contents', 1);
    }

    public function test_import_rejects_an_incomplete_package_before_writing(): void
    {
        $manifest = require database_path('data/content/marketing-course/manifest.php');
        $manifest['lessons'] = array_slice($manifest['lessons'], 0, 19);
        $path = storage_path('framework/testing/incomplete-marketing-course.php');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, "<?php\n\nreturn ".var_export($manifest, true).";\n");

        try {
            app(MarketingCourseImporter::class)->import(false, $path);
            $this->fail('The importer should reject an incomplete package.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('20', $exception->getMessage());
        } finally {
            File::delete($path);
        }

        $this->assertDatabaseCount('contents', 0);
    }
}
