<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Reports\BuildProjectReportAction;
use App\Application\Reports\ProjectDossierBuilder;
use App\Http\Controllers\Api\V1\Concerns\ResolvesCurrentProject;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\ProjectDossierController as WebDossierController;
use App\Http\Controllers\Web\ProjectReportController as WebReportController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * تقرير المشروع الشامل + دليل المشروع (dossier) للموبايل.
 * PDF يُفوَّض لمتحكمات الويب نفسها (session-free) — نفس الوثيقة تماماً.
 */
class ProjectReportController extends Controller
{
    use ResolvesCurrentProject;

    public function report(Request $request, BuildProjectReportAction $action): JsonResponse
    {
        $project = $this->currentProject();
        $this->authorize('view', $project);

        $report = $action->handle($project, $request->boolean('fresh'), allowBlocking: false);

        return response()->json(['data' => $report]);
    }

    public function reportPdf(Request $request): Response
    {
        $project = $this->currentProject();
        $this->authorize('view', $project);

        return app()->call(
            [app(WebReportController::class), 'exportPdf'],
            ['project' => $project],
        );
    }

    public function dossier(Request $request, ProjectDossierBuilder $builder): JsonResponse
    {
        $project = $this->currentProject();
        $this->authorize('view', $project);

        return response()->json(['data' => $builder->build($project)]);
    }

    public function dossierPdf(Request $request): Response
    {
        $project = $this->currentProject();
        $this->authorize('view', $project);

        return app()->call(
            [app(WebDossierController::class), 'exportPdf'],
            ['project' => $project],
        );
    }
}
