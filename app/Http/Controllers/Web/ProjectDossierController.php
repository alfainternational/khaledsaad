<?php

namespace App\Http\Controllers\Web;

use App\Application\Reports\ProjectDossierBuilder;
use App\Domain\Project\Models\Project;
use App\Http\Controllers\Controller;
use App\Support\Agency\WhiteLabelResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Mpdf\Mpdf;

/**
 * دليل المشروع: تجميع كل بيانات المشروع وإجاباته الخام في وثيقة واحدة قابلة
 * للطباعة (خطوة مستقلة عن التقرير التحليلي). حتمية بالكامل — بلا LLM.
 */
class ProjectDossierController extends Controller
{
    public function show(Request $request, Project $project, ProjectDossierBuilder $builder): View
    {
        $workspace = $project->workspace;
        abort_unless($workspace !== null && $request->user()?->can('view', $workspace), 404);

        return view('app.reports.dossier', [
            'project' => $project,
            'dossier' => $builder->build($project),
        ]);
    }

    /**
     * تصدير الدليل الخام إلى PDF مُعلّم (white-label للوكالات).
     */
    public function exportPdf(
        Request $request,
        Project $project,
        ProjectDossierBuilder $builder,
        WhiteLabelResolver $whiteLabel,
    ): Response {
        $workspace = $project->workspace;
        abort_unless($workspace !== null && $request->user()?->can('view', $workspace), 404);

        $dossier = $builder->build($project);
        $branding = $whiteLabel->for($workspace);

        $html = view('app.reports.dossier-pdf', compact('project', 'dossier', 'branding'))->render();

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

        $filename = 'dossier-'.Str::slug($project->name).'.pdf';

        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
