<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\Growth\SyntheticAudience;
use App\Services\Messaging\PersonaMessageProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * مختبر الجمهور: بناء لوحة الشخصيات واختبار الرسائل عليها قبل الإنفاق.
 */
class AudienceLabController extends Controller
{
    use ResolvesWorkspace;

    public function __construct(
        private readonly SyntheticAudience $audience,
        private readonly PersonaMessageProfileService $profiles,
    ) {}

    public function show(Request $request, Project $project): View
    {
        $this->authorizeProject($request, $project);

        $panel = $project->personaPanel?->load('tests');

        return view('app.audience.lab', [
            'project' => $project,
            'panel' => $panel,
            // المفتاح يُحسب هنا لا في القالب: العرض لا يستدعي خدمات.
            'personaKeys' => collect($panel?->personas ?? [])
                ->map(fn (array $persona) => $this->profiles->keyFor($persona))->all(),
            'tests' => $panel?->tests()->with('user:id,name')->limit(10)->get() ?? collect(),
        ]);
    }

    public function buildPanel(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);

        $this->audience->buildPanel($project);

        return redirect()
            ->route('app.audience.show', $project)
            ->with('status', __('لوحة جمهورك جاهزة — اكتب رسالة واختبرها عليهم.'));
    }

    public function test(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);

        $validated = $request->validate([
            'message' => 'required|string|min:10|max:1000',
        ], [
            'message.min' => __('اكتب الرسالة كما ستنشرها فعلًا — عشرة أحرف على الأقل.'),
        ]);

        $panel = $project->personaPanel;

        if ($panel === null) {
            return back()->withErrors(['message' => __('ابنِ لوحة الجمهور أولًا.')]);
        }

        try {
            $this->audience->test($panel, $validated['message'], $request->user());
        } catch (Throwable) {
            // رد فعل مُخترع أسوأ من الاعتذار: نقولها صراحة ولا نلفّق.
            return back()->withErrors([
                'message' => __('تعذّر إجراء الاختبار الآن. رسالتك لم تُفقد — أعد المحاولة بعد قليل.'),
            ])->withInput();
        }

        return redirect()
            ->route('app.audience.show', $project)
            ->with('status', __('جمهورك قال رأيه — النتيجة بالأسفل.'));
    }
}
