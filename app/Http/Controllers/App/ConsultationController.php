<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\ConsultationConflict;
use App\Models\ConsultationEvidence;
use App\Models\ConsultationSession;
use App\Models\Project;
use App\Modules\Intake\ConsultationEvidenceService;
use App\Modules\Intake\ConsultationPresenter;
use App\Modules\Intake\ConsultationPrivacyService;
use App\Modules\Intake\ConsultationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConsultationController extends Controller
{
    use ResolvesWorkspace;

    public function __construct(
        private readonly ConsultationService $service,
        private readonly ConsultationPresenter $presenter,
        private readonly ConsultationPrivacyService $privacy,
        private readonly ConsultationEvidenceService $evidence,
    ) {}

    public function index(Request $request): View
    {
        $projects = Project::whereHas('workspace', fn ($query) => $query->where('owner_id', $request->user()->id))
            ->with(['consultationSessions' => fn ($query) => $query->latest('id')])
            ->latest('id')->get();

        return view('app.consultations.index', [
            'projects' => $projects->map(function (Project $project): array {
                $latest = $project->consultationSessions->first();

                return [
                    'slug' => $project->slug,
                    'name' => $project->name,
                    'stage' => $project->stage,
                    'consultation' => $latest ? [
                        'uuid' => $latest->uuid,
                        'status' => $latest->status,
                        'answered' => $latest->questions_answered,
                    ] : null,
                ];
            })->all(),
        ]);
    }

    public function start(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $validated = $request->validate(['depth' => 'nullable|in:quick,standard,deep']);
        $session = $this->service->start($project, $request->user(), $validated['depth'] ?? 'standard');

        return redirect()->route('app.consultations.show', $session);
    }

    public function show(Request $request, ConsultationSession $consultation): View
    {
        $this->authorizeProject($request, $consultation->project);

        return view('app.consultations.show', ['consultation' => $this->presenter->show($consultation)]);
    }

    public function answer(Request $request, ConsultationSession $consultation): RedirectResponse
    {
        $this->authorizeProject($request, $consultation->project);
        $question = $consultation->currentQuestion?->load('definition');
        abort_if($question === null, 404);
        $validated = $request->validate(['value' => 'nullable', 'unknown' => 'nullable|boolean', 'skipped' => 'nullable|boolean']);
        $this->service->answer($consultation, $question, $validated);

        return redirect()->route('app.consultations.show', $consultation);
    }

    public function revise(Request $request, ConsultationSession $consultation, string $question): RedirectResponse
    {
        $this->authorizeProject($request, $consultation->project);
        $answer = $consultation->answers()->with('questionVersion.definition')->get()
            ->first(fn ($item) => $item->questionVersion->definition->key === $question);
        abort_if($answer === null, 404);
        $validated = $request->validate(['value' => 'nullable', 'unknown' => 'nullable|boolean', 'skipped' => 'nullable|boolean']);
        $this->service->revise($consultation, $answer->questionVersion, $validated);

        return redirect()->route('app.consultations.show', $consultation)->with('status', __('صُححت الإجابة وأُعيد حساب نطاق التشخيص.'));
    }

    public function confirm(Request $request, ConsultationSession $consultation): RedirectResponse
    {
        $this->authorizeProject($request, $consultation->project);
        $this->service->confirm($consultation, $request->user());

        return redirect()->route('app.consultations.show', $consultation)->with('status', __('بدأ التحليل الشامل. سنعرض التقرير هنا عند اكتماله.'));
    }

    public function retry(Request $request, ConsultationSession $consultation): RedirectResponse
    {
        $this->authorizeProject($request, $consultation->project);
        $this->service->retry($consultation, $request->user());

        return redirect()->route('app.consultations.show', $consultation)->with('status', __('أُعيد تشغيل التحليل.'));
    }

    public function review(Request $request, ConsultationSession $consultation): RedirectResponse
    {
        $this->authorizeProject($request, $consultation->project);
        $this->service->review($consultation);

        return redirect()->route('app.consultations.show', $consultation);
    }

    public function resolveConflict(Request $request, ConsultationSession $consultation, ConsultationConflict $conflict): RedirectResponse
    {
        $this->authorizeProject($request, $consultation->project);
        $validated = $request->validate(['resolution' => 'required|string|min:5|max:1000']);
        $this->service->resolveConflict($consultation, $conflict, $validated['resolution']);

        return redirect()->route('app.consultations.show', $consultation)->with('status', __('حُفظ التوضيح.'));
    }

    public function export(Request $request, ConsultationSession $consultation)
    {
        $this->authorizeProject($request, $consultation->project);
        $payload = json_encode($this->privacy->export($consultation), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

        return response()->streamDownload(fn () => print ($payload), "consultation-{$consultation->uuid}.json", ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    public function destroy(Request $request, ConsultationSession $consultation): RedirectResponse
    {
        $this->authorizeProject($request, $consultation->project);
        $project = $consultation->project;
        $this->privacy->delete($consultation);

        return redirect()->route('app.projects.show', $project)->with('status', __('حُذفت بيانات الاستشارة مع بقاء المشروع والتقارير المنشورة.'));
    }

    public function uploadEvidence(Request $request, ConsultationSession $consultation): RedirectResponse
    {
        $this->authorizeProject($request, $consultation->project);
        $validated = $request->validate(['file' => 'required|file|max:10240|mimes:pdf,doc,docx,xls,xlsx,csv,txt,png,jpg,jpeg,webp']);
        $this->evidence->store($consultation, $validated['file']);

        return back()->with('status', __('رُفع الدليل وأُضيف إلى سجل الاستشارة.'));
    }

    public function deleteEvidence(Request $request, ConsultationSession $consultation, ConsultationEvidence $evidence): RedirectResponse
    {
        $this->authorizeProject($request, $consultation->project);
        $this->evidence->delete($consultation, $evidence);

        return back()->with('status', __('حُذف الدليل.'));
    }
}
