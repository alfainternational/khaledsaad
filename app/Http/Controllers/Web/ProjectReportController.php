<?php

namespace App\Http\Controllers\Web;

use App\Application\Reports\BuildProjectReportAction;
use App\Domain\Project\Models\Project;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * التقرير الشامل على مستوى المشروع: يجمع كل الأدوات المنجَزة في خطة استراتيجية
 * واحدة مترابطة (تركيب عبر LLM مبني على المخرجات الفعلية + هيكل محلي حتمي).
 */
class ProjectReportController extends Controller
{
    public function show(Request $request, Project $project, BuildProjectReportAction $action): View
    {
        // يُصرَّح بناءً على مساحة المشروع نفسه (لا المساحة "الحالية") — فيرى المستخدم
        // تقارير أي مشروع يملك صلاحية مساحته حتى لو كان متصفّحاً من مساحة أخرى.
        $workspace = $project->workspace;
        abort_unless($workspace !== null && $request->user()?->can('view', $workspace), 404);

        $report = $action->handle($project, $request->boolean('fresh'));

        return view('app.reports.project', [
            'project' => $project,
            'report' => $report,
        ]);
    }
}
