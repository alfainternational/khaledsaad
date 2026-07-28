<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ConsultationConflict;
use App\Models\ConsultationEvidence;
use App\Models\ConsultationSession;
use App\Models\Project;
use App\Modules\Intake\ConsultationEvidenceService;
use App\Modules\Intake\ConsultationPresenter;
use App\Modules\Intake\ConsultationPrivacyService;
use App\Modules\Intake\ConsultationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ConsultationController extends Controller
{
    public function __construct(
        private readonly ConsultationService $service,
        private readonly ConsultationPresenter $presenter,
        private readonly ConsultationPrivacyService $privacy,
        private readonly ConsultationEvidenceService $evidence,
    ) {}

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorizeOwned($request, $project);
        $validated = $request->validate(['depth' => 'nullable|in:quick,standard,deep']);
        $session = $this->service->start($project, $request->user(), $validated['depth'] ?? 'standard');

        return response()->json(['data' => $this->presenter->show($session)], 201);
    }

    public function show(Request $request, ConsultationSession $consultation): JsonResponse
    {
        $this->authorizeOwned($request, $consultation->project);

        return response()->json(['data' => $this->presenter->show($consultation)]);
    }

    public function answer(Request $request, ConsultationSession $consultation, string $question): JsonResponse
    {
        $this->authorizeOwned($request, $consultation->project);
        $current = $consultation->currentQuestion?->load('definition');
        $previous = $consultation->answers()->with('questionVersion.definition')
            ->get()->first(fn ($answer) => $answer->questionVersion->definition->key === $question);
        if (($current === null || $current->definition->key !== $question) && $previous === null) {
            throw new NotFoundHttpException;
        }
        $validated = $request->validate(['value' => 'nullable', 'unknown' => 'nullable|boolean', 'skipped' => 'nullable|boolean']);
        $session = $current !== null && $current->definition->key === $question
            ? $this->service->answer($consultation, $current, $validated)
            : $this->service->revise($consultation, $previous->questionVersion, $validated);

        return response()->json(['data' => $this->presenter->show($session), 'message' => 'حُفظت إجابتك.']);
    }

    public function confirm(Request $request, ConsultationSession $consultation): JsonResponse
    {
        $this->authorizeOwned($request, $consultation->project);
        $session = $this->service->confirm($consultation, $request->user());

        return response()->json(['data' => $this->presenter->show($session), 'message' => 'بدأ التحليل الشامل.']);
    }

    public function retry(Request $request, ConsultationSession $consultation): JsonResponse
    {
        $this->authorizeOwned($request, $consultation->project);
        $session = $this->service->retry($consultation, $request->user());

        return response()->json(['data' => $this->presenter->show($session), 'message' => 'أُعيد تشغيل التحليل.']);
    }

    public function review(Request $request, ConsultationSession $consultation): JsonResponse
    {
        $this->authorizeOwned($request, $consultation->project);

        return response()->json(['data' => $this->presenter->show($this->service->review($consultation))]);
    }

    public function status(Request $request, ConsultationSession $consultation): JsonResponse
    {
        $this->authorizeOwned($request, $consultation->project);

        return response()->json(['data' => $this->presenter->show($consultation->refresh())]);
    }

    public function resolveConflict(Request $request, ConsultationSession $consultation, ConsultationConflict $conflict): JsonResponse
    {
        $this->authorizeOwned($request, $consultation->project);
        $validated = $request->validate(['resolution' => 'required|string|min:5|max:1000']);
        $session = $this->service->resolveConflict($consultation, $conflict, $validated['resolution']);

        return response()->json(['data' => $this->presenter->show($session), 'message' => 'حُفظ توضيح التعارض.']);
    }

    public function export(Request $request, ConsultationSession $consultation): JsonResponse
    {
        $this->authorizeOwned($request, $consultation->project);

        return response()->json(['data' => $this->privacy->export($consultation)]);
    }

    public function destroy(Request $request, ConsultationSession $consultation): JsonResponse
    {
        $this->authorizeOwned($request, $consultation->project);
        $this->privacy->delete($consultation);

        return response()->json(status: 204);
    }

    public function uploadEvidence(Request $request, ConsultationSession $consultation): JsonResponse
    {
        $this->authorizeOwned($request, $consultation->project);
        $validated = $request->validate(['file' => 'required|file|max:10240|mimes:pdf,doc,docx,xls,xlsx,csv,txt,png,jpg,jpeg,webp']);
        $this->evidence->store($consultation, $validated['file']);

        return response()->json(['data' => $this->presenter->show($consultation->refresh()), 'message' => 'رُفع الدليل بأمان.'], 201);
    }

    public function deleteEvidence(Request $request, ConsultationSession $consultation, ConsultationEvidence $evidence): JsonResponse
    {
        $this->authorizeOwned($request, $consultation->project);
        $this->evidence->delete($consultation, $evidence);

        return response()->json(['data' => $this->presenter->show($consultation->refresh())]);
    }

    private function authorizeOwned(Request $request, Project $project): void
    {
        if ($request->user()?->can('view', $project) !== true) {
            throw new NotFoundHttpException;
        }
    }
}
