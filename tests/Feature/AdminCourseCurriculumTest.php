<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\CourseSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCourseCurriculumTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_builds_an_ordered_course_curriculum(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $course = Content::query()->create([
            'type' => Content::TYPE_COURSE,
            'title' => 'دورة التسويق',
            'slug' => 'marketing-course',
            'created_by' => $admin->id,
        ]);
        $lesson = Content::query()->create([
            'type' => Content::TYPE_LESSON,
            'title' => 'الدرس الأول',
            'slug' => 'lesson-one',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post(route('admin.content.sections.store', $course), [
            'title' => 'الانطلاقة',
            'description' => 'ابدأ من هنا',
        ])->assertRedirect();

        $section = CourseSection::query()->sole();

        $this->actingAs($admin)->post(route('admin.content.sections.items.store', [$course, $section]), [
            'content_id' => $lesson->id,
        ])->assertRedirect();

        $this->assertSame($course->id, $section->course_id);
        $this->assertSame($lesson->id, $section->items()->sole()->id);
        $this->assertSame(1, $section->items()->sole()->pivot->position);

        $this->actingAs($admin)
            ->get(route('admin.content.curriculum', $course))
            ->assertOk()
            ->assertSee('منهج الدورة')
            ->assertSee('الانطلاقة')
            ->assertSee('الدرس الأول');
    }

    public function test_sections_belong_only_to_courses_and_items_must_be_lessons_or_lectures(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $article = Content::query()->create([
            'title' => 'مقال',
            'slug' => 'article',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post(route('admin.content.sections.store', $article), [
            'title' => 'درس آخر',
        ])->assertNotFound();

        $course = Content::query()->create([
            'type' => Content::TYPE_COURSE,
            'title' => 'دورة',
            'slug' => 'course',
            'created_by' => $admin->id,
        ]);
        $section = CourseSection::query()->create([
            'course_id' => $course->id,
            'title' => 'قسم',
            'position' => 1,
        ]);

        $this->actingAs($admin)->post(route('admin.content.sections.items.store', [$course, $section]), [
            'content_id' => $article->id,
        ])->assertSessionHasErrors('content_id');

        $this->assertDatabaseCount('course_section_items', 0);
    }
}
