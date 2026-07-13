<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\AI\GenerateTemplateDraftAction;
use App\Domain\AI\Models\AIGeneration;
use App\Domain\AI\Models\AITemplate;
use App\Domain\Project\Models\Project;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\GenerateStudioOutputRequest;
use App\Http\Resources\V1\StudioGenerationResource;
use App\Support\AI\StudioGenerationExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Mpdf\Mpdf;

class StudioGenerationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');
        $this->authorize('view', $workspace);

        $rows = AIGeneration::query()
            ->where('workspace_id', $workspace->id)
            ->with('template')
            ->latest()
            ->paginate(min($request->integer('per_page', 15), 50));

        return StudioGenerationResource::collection($rows);
    }

    public function store(
        GenerateStudioOutputRequest $request,
        GenerateTemplateDraftAction $action,
    ): JsonResponse {
        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');
        $this->authorize('useTools', $workspace);

        $template = AITemplate::query()->findOrFail($request->validated('template_id'));
        $projectId = $request->validated('project_id');
        $project = $projectId
            ? Project::query()->where('workspace_id', $workspace->id)->findOrFail($projectId)
            : null;

        // التوليد قد يستدعي LLM مرتين (مسودة + مراجعة جودة) — لا يقتله حد PHP الافتراضي.
        set_time_limit(180);

        $generation = $action->handle(
            workspace: $workspace,
            template: $template,
            project: $project?->load('client'),
            actor: $request->user(),
            brief: $request->validated('brief'),
        );

        return (new StudioGenerationResource($generation->load('template')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request): StudioGenerationResource
    {
        $generation = $this->resolve((string) $request->route('generationPublicId'));

        return new StudioGenerationResource($generation->load('template'));
    }

    public function export(Request $request): Response
    {
        $generation = $this->resolve((string) $request->route('generationPublicId'));
        $generation->load('template');

        return match (strtolower((string) $request->route('format'))) {
            'md', 'markdown' => $this->exportMarkdown($generation),
            'html' => $this->exportHtml($generation),
            'pdf' => $this->exportPdf($generation),
            default => abort(404),
        };
    }

    public function destroy(Request $request): Response
    {
        $generation = $this->resolve((string) $request->route('generationPublicId'));
        $generation->delete();

        return response()->noContent();
    }

    /**
     * يحل التوليد ضمن مساحة العمل الحالية (عزل صارم).
     */
    private function resolve(string $publicId): AIGeneration
    {
        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');

        return AIGeneration::query()
            ->where('workspace_id', $workspace->id)
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    private function exportMarkdown(AIGeneration $generation): Response
    {
        $name = StudioGenerationExporter::suggestedFilename($generation, 'md');

        return response(StudioGenerationExporter::markdownBody($generation), 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
        ]);
    }

    private function exportHtml(AIGeneration $generation): Response
    {
        $name = StudioGenerationExporter::suggestedFilename($generation, 'html');

        return response(StudioGenerationExporter::printableHtml($generation), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
        ]);
    }

    private function exportPdf(AIGeneration $generation): Response
    {
        $html = StudioGenerationExporter::printableHtml($generation);

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 14,
            'margin_bottom' => 14,
            'directionality' => 'rtl',
            'default_font' => 'dejavusanscondensed',
            'autoArabic' => true,
            'useSubstitutions' => true,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);
        $mpdf->WriteHTML($html);

        $name = StudioGenerationExporter::suggestedFilename($generation, 'pdf');

        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
        ]);
    }
}
