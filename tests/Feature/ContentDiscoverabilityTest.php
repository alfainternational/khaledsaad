<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Support\Content\ContentStructuredData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentDiscoverabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_lesson_emits_parseable_article_learning_and_breadcrumb_schema(): void
    {
        $this->artisan('content:import-marketing-course', ['--publish' => true])->assertSuccessful();
        $content = Content::query()->where('learning_order', 1)->sole();
        $html = $this->get(route('content.show', $content))
            ->assertOk()
            ->assertSee('<meta property="og:type" content="article">', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
            ->getContent();

        preg_match('/<script type="application\/ld\+json">\s*(.*?)\s*<\/script>/su', $html, $matches);
        $schema = json_decode($matches[1] ?? '', true, flags: JSON_THROW_ON_ERROR);
        $types = collect($schema['@graph'])->pluck('@type')->flatten()->all();

        $this->assertSame('https://schema.org', $schema['@context']);
        $this->assertContains('Article', $types);
        $this->assertContains('LearningResource', $types);
        $this->assertContains('BreadcrumbList', $types);
        $this->assertStringNotContainsString("'@context' =>", $html);
    }

    public function test_llms_and_sitemap_list_all_published_learning_pages(): void
    {
        $this->artisan('content:import-marketing-course', ['--publish' => true])->assertSuccessful();

        $llms = $this->get('/llms.txt')->assertOk()->assertHeader('content-type', 'text/plain; charset=UTF-8');
        $sitemap = $this->get('/sitemap.xml')->assertOk();

        foreach (Content::query()->whereNotNull('learning_order')->get() as $content) {
            $llms->assertSee(route('content.show', $content), false);
            $sitemap->assertSee(route('content.show', $content), false);
        }
    }

    public function test_structured_data_escapes_script_boundaries_and_ignores_malformed_faq_items(): void
    {
        $content = new Content([
            'title' => '</script><script>window.pwned=1</script>',
            'slug' => 'safe-schema',
            'source_key' => 'marketing-course-99',
            'learning_order' => 99,
            'learning_meta' => ['faq' => ['broken', ['question' => 'سؤال فقط']]],
        ]);
        $json = app(ContentStructuredData::class)->forContent($content, ['enabled' => true, 'total' => 1]);

        $this->assertStringNotContainsString('</script><script>window.pwned=1</script>', $json);
        $schema = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('</script><script>window.pwned=1</script>', collect($schema['@graph'])->firstWhere('@type', 'Article')['headline']);
        $this->assertNotContains('FAQPage', collect($schema['@graph'])->pluck('@type')->all());
    }

    public function test_locked_content_does_not_leak_faq_answers_in_structured_data(): void
    {
        $secret = 'إجابة خاصة لا تظهر قبل فتح الدرس';
        $content = new Content([
            'title' => 'درس مقفل',
            'slug' => 'locked-schema',
            'source_key' => 'marketing-course-98',
            'learning_order' => 98,
            'learning_meta' => ['faq' => [['question' => 'السؤال', 'answer' => $secret]]],
        ]);
        $json = app(ContentStructuredData::class)->forContent(
            $content,
            ['enabled' => true, 'total' => 1],
            false,
        );

        $this->assertStringNotContainsString($secret, $json);
        $schema = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        $this->assertNotContains('FAQPage', collect($schema['@graph'])->pluck('@type')->all());
    }

    public function test_llms_file_excludes_other_learning_series(): void
    {
        $this->artisan('content:import-marketing-course', ['--publish' => true])->assertSuccessful();
        $other = Content::query()->create([
            'title' => 'درس من سلسلة أخرى',
            'slug' => 'other-series',
            'source_key' => 'other-series-01',
            'learning_order' => 1,
            'status' => Content::STATUS_PUBLISHED,
            'access_level' => Content::ACCESS_PUBLIC,
            'published_at' => now(),
        ]);

        $this->get('/llms.txt')
            ->assertOk()
            ->assertDontSee(route('content.show', $other), false);
    }
}
