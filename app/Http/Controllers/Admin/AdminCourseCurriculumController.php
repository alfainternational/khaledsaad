<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CourseSectionRequest;
use App\Models\Content;
use App\Models\CourseSection;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminCourseCurriculumController extends Controller
{
    public function show(Content $course): View
    {
        $this->ensureCourse($course);

        return view('admin.content.curriculum', [
            'course' => $course->load('sections.items'),
            'eligibleItems' => Content::query()
                ->whereIn('type', [Content::TYPE_LESSON, Content::TYPE_LECTURE])
                ->where('status', '!=', Content::STATUS_ARCHIVED)
                ->orderBy('title')
                ->get(),
        ]);
    }

    public function storeSection(CourseSectionRequest $request, Content $course): RedirectResponse
    {
        $this->ensureCourse($course);

        $course->sections()->create($request->validated() + [
            'position' => ((int) $course->sections()->max('position')) + 1,
        ]);

        return back()->with('success', '????? ??? ??????.');
    }

    public function updateSection(CourseSectionRequest $request, Content $course, CourseSection $section): RedirectResponse
    {
        $this->ensureSection($course, $section);
        $section->update($request->validated());

        return back()->with('success', '???? ?????.');
    }

    public function destroySection(Content $course, CourseSection $section): RedirectResponse
    {
        $this->ensureSection($course, $section);
        $section->delete();

        return back()->with('success', '???? ????? ?? ??????.');
    }

    public function storeItem(Request $request, Content $course, CourseSection $section): RedirectResponse
    {
        $this->ensureSection($course, $section);

        $data = $request->validate([
            'content_id' => [
                'required',
                Rule::exists('contents', 'id')->where(fn (Builder $query) => $query->whereIn('type', [
                    Content::TYPE_LESSON,
                    Content::TYPE_LECTURE,
                ])),
                Rule::unique('course_section_items', 'content_id')->where('course_section_id', $section->id),
            ],
        ]);

        $section->items()->attach($data['content_id'], [
            'position' => ((int) $section->items()->max('course_section_items.position')) + 1,
        ]);

        return back()->with('success', '????? ?????? ??? ?????.');
    }

    public function destroyItem(Content $course, CourseSection $section, Content $item): RedirectResponse
    {
        $this->ensureSection($course, $section);
        $section->items()->detach($item->id);

        return back()->with('success', '????? ?????? ?? ?????.');
    }

    private function ensureCourse(Content $course): void
    {
        abort_unless($course->type === Content::TYPE_COURSE, 404);
    }

    private function ensureSection(Content $course, CourseSection $section): void
    {
        $this->ensureCourse($course);
        abort_unless($section->course_id === $course->id, 404);
    }
}
