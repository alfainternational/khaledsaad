<?php

namespace App\Http\Controllers\Web;

use App\Application\AI\GenerateTemplateDraftAction;
use App\Domain\AI\Kernel\Agents\SpecialistReviewService;
use App\Domain\AI\Models\AIGeneration;
use App\Domain\AI\Models\AITemplate;
use App\Domain\Project\Models\Project;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithWorkspaceContext;
use App\Http\Requests\Web\GenerateStudioOutputRequest;
use App\Support\AI\StudioGenerationExporter;
use App\Support\AI\StudioContentRenderer;
use App\Support\AI\StudioMarkdownSections;
use App\Support\Ui\FlashMessageCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Mpdf\Mpdf;

class StudioGenerationController extends Controller
{
    use InteractsWithWorkspaceContext;

    public function store(
        GenerateStudioOutputRequest $request,
        GenerateTemplateDraftAction $action,
        FlashMessageCatalog $flash,
    ): RedirectResponse {
        $workspace = $this->currentWorkspace($request);
        $template = AITemplate::query()->findOrFail($request->validated('template_id'));
        $projectId = $request->validated('project_id');
        $project = $projectId
            ? Project::query()->where('workspace_id', $workspace->id)->findOrFail($projectId)
            : null;

        $generation = $action->handle(
            workspace: $workspace,
            template: $template,
            project: $project?->load('client'),
            actor: $request->user(),
            brief: $request->validated('brief'),
        );

        return redirect()
            ->route('studio.generations.show', $generation)
            ->with('status', $flash->studioDraftGenerated());
    }

    public function show(
        Request $request,
        AIGeneration $aiGeneration,
        SpecialistReviewService $specialistReview,
    ): View {
        $workspace = $this->currentWorkspace($request);
        abort_unless($aiGeneration->workspace_id === $workspace->id, 403);

        $aiGeneration->load(['template', 'project.client', 'author', 'workspace']);

        // content_creator + brand_guardian: مراجعة صياغة عربية محلية للمخرَج المولّد.
        $review = $specialistReview->review(
            (string) ($aiGeneration->output ?? ''),
            [SpecialistReviewService::ASPECT_LOCALIZATION],
        );

        $sections = collect(StudioMarkdownSections::split($aiGeneration->output ?? ''))
            ->values()
            ->map(function (array $section, int $index): array {
                $body = (string) ($section['body'] ?? '');

                return [
                    'id' => 'studio-section-'.($index + 1),
                    'title' => (string) ($section['title'] ?? ''),
                    'body' => $body,
                    'excerpt' => StudioContentRenderer::excerpt($body),
                    'html' => StudioContentRenderer::render($body),
                ];
            })
            ->all();

        return view('app.studio-generation', [
            'generation' => $aiGeneration,
            'sections' => $sections,
            'specialistReview' => $review['panels'] === [] ? null : $review,
        ]);
    }

    public function export(Request $request, AIGeneration $aiGeneration, string $format): Response
    {
        $workspace = $this->currentWorkspace($request);
        abort_unless($aiGeneration->workspace_id === $workspace->id, 403);

        $aiGeneration->load('template');

        $format = strtolower($format);

        return match ($format) {
            'md', 'markdown' => $this->exportMarkdown($aiGeneration),
            'html' => $this->exportHtml($aiGeneration),
            'pdf' => $this->exportPdf($aiGeneration),
            default => abort(404),
        };
    }

    public function destroy(Request $request, AIGeneration $aiGeneration, FlashMessageCatalog $flash): RedirectResponse
    {
        $workspace = $this->currentWorkspace($request);
        abort_unless($aiGeneration->workspace_id === $workspace->id, 403);

        $aiGeneration->delete();

        return redirect()->route('studio.index')->with('status', $flash->deleted('المخرج'));
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

        // mPDF: dejavusanscondensed + substitutions improves Arabic coverage vs. dejavusans alone.
        // If glyphs still fail on a host, add a TTF under storage/fonts and register via fontDir/fontdata.
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
        $binary = $mpdf->Output('', 'S');

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
        ]);
    }
}
