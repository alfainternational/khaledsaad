<?php

namespace Tests\Unit\Modules\Learning;

use App\Modules\Learning\MarketingCourseCatalog;
use Tests\TestCase;

class MarketingCourseCatalogTest extends TestCase
{
    public function test_the_catalog_contains_all_twenty_lessons_and_forty_two_exercises(): void
    {
        $catalog = app(MarketingCourseCatalog::class);

        $this->assertCount(20, $catalog->lessons());
        $this->assertCount(42, $catalog->exercises());
        $this->assertSame(range(1, 20), array_column($catalog->lessons(), 'number'));
    }

    public function test_every_exercise_has_a_stable_unique_key_questions_and_a_deliverable(): void
    {
        $exercises = app(MarketingCourseCatalog::class)->exercises();
        $keys = array_column($exercises, 'key');

        $this->assertCount(count($keys), array_unique($keys));

        foreach ($exercises as $exercise) {
            $this->assertNotEmpty($exercise['title']);
            $this->assertNotEmpty($exercise['purpose']);
            $this->assertNotEmpty($exercise['deliverable']);
            $this->assertIsInt($exercise['duration_minutes']);
            $this->assertNotEmpty($exercise['source_url']);
            $this->assertIsArray($exercise['brain_dependencies']);
            $this->assertNotEmpty($exercise['questions']);

            $questionKeys = [];

            foreach ($exercise['questions'] as $question) {
                $this->assertNotEmpty($question['key']);
                $this->assertNotEmpty($question['label']);
                $this->assertNotEmpty($question['rubric']);
                $this->assertArrayHasKey('help', $question);
                $this->assertArrayHasKey('example', $question);
                $this->assertArrayHasKey('type', $question);
                $this->assertArrayHasKey('required', $question);
                $this->assertArrayHasKey('min', $question);
                $questionKeys[] = $question['key'];
            }

            $this->assertCount(count($questionKeys), array_unique($questionKeys));
        }
    }

    public function test_every_lesson_links_back_to_its_published_source(): void
    {
        foreach (app(MarketingCourseCatalog::class)->lessons() as $lesson) {
            $this->assertSame(
                'https://khaledsaad.net',
                parse_url($lesson['source_url'], PHP_URL_SCHEME).'://'.parse_url($lesson['source_url'], PHP_URL_HOST),
            );
        }
    }
}
