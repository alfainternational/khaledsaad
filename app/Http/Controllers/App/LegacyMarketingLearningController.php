<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LegacyMarketingLearningController extends Controller
{
    use ResolvesWorkspace;

    public function __construct(private readonly MarketingCourseController $course) {}

    public function index(Request $request, Project $project): View
    {
        $this->context($request, $project);

        return $this->course->index($request);
    }

    public function exercise(Request $request, Project $project, string $exercise): View
    {
        $this->context($request, $project);

        return $this->course->exercise($request, $exercise);
    }

    public function save(Request $request, Project $project, string $exercise): RedirectResponse
    {
        $this->context($request, $project);

        return $this->course->save($request, $exercise);
    }

    public function submit(Request $request, Project $project, string $exercise): RedirectResponse
    {
        $this->context($request, $project);

        return $this->course->submit($request, $exercise);
    }

    public function result(Request $request, Project $project, string $exercise): View
    {
        $this->context($request, $project);

        return $this->course->result($request, $exercise);
    }

    public function retry(Request $request, Project $project, string $exercise): RedirectResponse
    {
        $this->context($request, $project);

        return $this->course->retry($request, $exercise);
    }

    private function context(Request $request, Project $project): void
    {
        $this->authorizeProject($request, $project);
        $request->query->set('project', $project->slug);
    }
}
