<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\Growth\GeoPackGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * حزمة الظهور للآلات: صفحة العرض والتوليد وتنزيل llms.txt.
 */
class GeoPackController extends Controller
{
    use ResolvesWorkspace;

    public function __construct(private readonly GeoPackGenerator $generator) {}

    public function show(Request $request, Project $project): View
    {
        $this->authorizeProject($request, $project);

        return view('app.geo.show', [
            'project' => $project,
            'pack' => $project->geoPack,
            'missing' => $this->generator->missingFields($project),
        ]);
    }

    public function generate(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);

        // بوابة الجاهزية: حزمة من ملف ناقص تعرّف الآلات على مشروع مشوّه.
        if ($this->generator->missingFields($project) !== []) {
            return back()->withErrors([
                'geo' => 'أكمل ملف المشروع أولًا — الحزمة تُبنى مما كتبته أنت.',
            ]);
        }

        $this->generator->generate($project);

        return redirect()
            ->route('app.geo.show', $project)
            ->with('status', 'حزمتك جاهزة. انسخ ما تحتاجه وضعه في موقعك.');
    }

    public function llms(Request $request, Project $project): Response
    {
        $this->authorizeProject($request, $project);

        $pack = $project->geoPack;

        abort_if($pack === null, 404);

        return response($pack->llms_txt, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="llms.txt"',
        ]);
    }
}
