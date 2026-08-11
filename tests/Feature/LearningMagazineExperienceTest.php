<?php

namespace Tests\Feature;

use App\Models\Content;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningMagazineExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_lesson_renders_outline_progress_tools_and_adjacent_navigation(): void
    {
        $this->artisan('content:import-marketing-course', ['--publish' => true])->assertSuccessful();

        $first = Content::query()->where('learning_order', 1)->sole();
        $second = Content::query()->where('learning_order', 2)->sole();

        $this->get(route('content.show', $first))
            ->assertOk()
            ->assertSee('data-reading-progress', false)
            ->assertSee('data-learning-article', false)
            ->assertSee('في هذا الدرس')
            ->assertSee('حفظ التقدم')
            ->assertSee('طباعة')
            ->assertSee(route('content.show', $second), false)
            ->assertSee('مهمة تطبيقية قبل الدردشة القادمة:');
    }

    public function test_learning_series_is_ordered_on_the_library_page(): void
    {
        $this->artisan('content:import-marketing-course', ['--publish' => true])->assertSuccessful();

        $response = $this->get(route('content.index', ['category' => 'تعلم-التسويق']))->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('الدرس 1', $html);
        $this->assertStringContainsString('الدرس 20', $html);
        $this->assertLessThan(
            strpos($html, 'معرض الدروس والتطبيقات'),
            strpos($html, 'قبل ما تفكر في الحملة الجاية'),
        );
    }

    public function test_learning_navigation_and_total_ignore_another_series_in_the_same_category(): void
    {
        $this->artisan('content:import-marketing-course', ['--publish' => true])->assertSuccessful();
        $first = Content::query()->where('source_key', 'marketing-course-01')->sole();
        $second = Content::query()->where('source_key', 'marketing-course-02')->sole();

        Content::query()->create([
            'category_id' => $first->category_id,
            'title' => 'سلسلة أخرى',
            'slug' => 'intruder-series',
            'source_key' => 'intruder-series-01',
            'learning_order' => 1,
            'status' => Content::STATUS_PUBLISHED,
            'access_level' => Content::ACCESS_PUBLIC,
            'published_at' => now(),
        ]);

        $this->get(route('content.show', $first))
            ->assertOk()
            ->assertSee('الدرس 1 من 20')
            ->assertSee(route('content.show', $second), false)
            ->assertDontSee('سلسلة أخرى');
    }

    public function test_library_uses_the_card_cover_derivative_and_descriptive_alt_text(): void
    {
        $this->artisan('content:import-marketing-course', ['--publish' => true])->assertSuccessful();

        $this->get(route('content.index', ['category' => 'تعلم-التسويق']))
            ->assertOk()
            ->assertSee('lesson-02-card.webp', false)
            ->assertSee('alt="رسم تحريري رمزي', false);
    }
}
