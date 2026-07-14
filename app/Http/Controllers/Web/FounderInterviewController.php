<?php

namespace App\Http\Controllers\Web;

use App\Application\Interview\RunFounderInterviewAction;
use App\Domain\AI\Speech\SpeechToTextContract;
use App\Domain\Project\Models\Project;
use App\Domain\WorkspaceData\Models\WorkspaceData;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithWorkspaceContext;
use App\Support\Interview\FounderInterviewCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * مقابلة المؤسِّس (المرحلة 4): صفحة محادثة واحدة تملأ أساس المشروع وتوزّعه على
 * القيم المعيارية التي تُلقّم الأدوات لاحقاً (Context Capture §21 + حلقة المرحلة 1).
 */
class FounderInterviewController extends Controller
{
    use InteractsWithWorkspaceContext;

    public function show(Request $request): View
    {
        $workspace = $this->currentWorkspace($request);
        $this->authorize('useTools', $workspace);

        $projects = Project::query()
            ->where('workspace_id', $workspace->id)
            ->orderBy('name')
            ->get();
        $currentProject = $projects->firstWhere('status', 'active') ?? $projects->first();

        $existing = [];
        if ($currentProject) {
            $existing = WorkspaceData::query()
                ->where('workspace_id', $workspace->id)
                ->where('project_id', $currentProject->id)
                ->whereIn('key', FounderInterviewCatalog::keys())
                ->get()
                ->mapWithKeys(fn (WorkspaceData $row): array => [
                    $row->key => (string) ($row->value_json['value'] ?? ''),
                ])
                ->all();
        }

        return view('app.interview.show', [
            'workspace' => $workspace,
            'projects' => $projects,
            'currentProject' => $currentProject,
            'questions' => FounderInterviewCatalog::questions(),
            'existing' => $existing,
            'voiceEnabled' => app(SpeechToTextContract::class)->isAvailable(),
        ]);
    }

    public function store(Request $request, RunFounderInterviewAction $action): RedirectResponse
    {
        $workspace = $this->currentWorkspace($request);
        $this->authorize('useTools', $workspace);

        $validated = $request->validate([
            'project_id' => ['required', 'integer'],
            'answers' => ['required', 'array'],
            'answers.*' => ['nullable', 'string', 'max:2000'],
        ]);

        $project = Project::query()
            ->where('workspace_id', $workspace->id)
            ->findOrFail($validated['project_id']);
        $this->authorize('view', $project);

        $result = $action->handle($workspace, $project, $request->user(), $validated['answers']);

        if ($result['count'] === 0) {
            return back()->withInput()->withErrors([
                'answers' => 'اكتب إجابة واحدة على الأقل حتى نحفظ أساس مشروعك.',
            ]);
        }

        return redirect()
            ->route('projects.show', $project)
            ->with('status', 'حفظنا أساس مشروعك من المقابلة. الأدوات ستقترح هذه الإجابات تلقائياً الآن.');
    }

    public function transcribe(Request $request, SpeechToTextContract $speech): JsonResponse
    {
        $workspace = $this->currentWorkspace($request);
        $this->authorize('useTools', $workspace);

        if (! $speech->isAvailable()) {
            return response()->json(['message' => 'خدمة الصوت غير مفعّلة حالياً.'], 422);
        }

        $maxKb = max(1, (int) round(((int) config('services.ai.speech.max_bytes', 20971520)) / 1024));
        $request->validate([
            'audio' => ['required', 'file', 'max:'.$maxKb, 'mimetypes:audio/webm,audio/ogg,audio/mpeg,audio/mp4,audio/wav,audio/x-wav,audio/mp3,video/webm'],
        ]);

        $file = $request->file('audio');
        $transcript = $speech->transcribe(
            (string) file_get_contents($file->getRealPath()),
            'audio.'.($file->getClientOriginalExtension() ?: 'webm'),
        );

        if ($transcript === null || trim($transcript) === '') {
            return response()->json(['message' => 'تعذّر تحويل الصوت إلى نص. حاول مرة أخرى.'], 422);
        }

        return response()->json(['transcript' => $transcript]);
    }
}
