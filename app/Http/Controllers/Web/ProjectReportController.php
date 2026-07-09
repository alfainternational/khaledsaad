<?php

namespace App\Http\Controllers\Web;

use App\Application\Reports\BuildProjectReportAction;
use App\Domain\Project\Models\Project;
use App\Http\Controllers\Controller;
use App\Support\Agency\WhiteLabelResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Mpdf\Mpdf;

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

        // لا نحجب طلب الويب على LLM: يُعرض التقرير المحلي فوراً ويُدفّأ التركيب
        // الذكي في الخلفية (تفادياً لمهلات المزوّد التي كانت تُنتج خطأ 500).
        $report = $action->handle($project, $request->boolean('fresh'), allowBlocking: false);

        return view('app.reports.project', [
            'project' => $project,
            'report' => $report,
        ]);
    }

    /**
     * تصدير احترافي إلى PDF مُعلّم (white-label للوكالات) — وثيقة جاهزة للعميل.
     */
    public function exportPdf(
        Request $request,
        Project $project,
        BuildProjectReportAction $action,
        WhiteLabelResolver $whiteLabel,
    ): Response {
        $workspace = $project->workspace;
        abort_unless($workspace !== null && $request->user()?->can('view', $workspace), 404);

        // تصدير PDF لا يحجب على LLM أيضاً: يستخدم التركيب المُكاش إن وُجد وإلا محلياً.
        $report = $action->handle($project, false, allowBlocking: false);
        $branding = $whiteLabel->for($workspace);

        $html = view('app.reports.pdf', compact('project', 'report', 'branding'))->render();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 14,
            'margin_bottom' => 16,
            'directionality' => 'rtl',
            'default_font' => 'dejavusanscondensed',
            'autoArabic' => true,
            'useSubstitutions' => true,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);
        $mpdf->WriteHTML($html);

        $filename = 'report-'.Str::slug($project->name).'.pdf';

        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
