<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ConsultationSession;
use App\Models\Project;
use App\Models\QuestionAssist;
use App\Models\ToolRun;
use App\Modules\Intake\Assist\AssistComposer;
use App\Modules\Intake\Assist\AssistDraft;
use App\Modules\Intake\Assist\QuestionDescriptor;
use App\Modules\Intake\Assist\QuestionLocator;
use App\Modules\Intake\Fitness\AnswerFitnessScorer;
use App\Modules\Shared\Text\ArabicText;
use App\Policies\ProjectOwnership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * مساعدة صاحب النشاط على سؤال واحد: دليل ومقترحات، وقياس كفاية ما كتبه.
 *
 * مسارٌ واحد لكل الأسطح لأن القاعدة واحدة: كل سؤال في كل قالب واستمارة، بلا
 * استثناء. مسارٌ لكل سطح كان سيعني منطقين للحراسة والتكلفة يتباعدان.
 *
 * **الفصل بين الفعلين مقصود:**
 *   - `store` يولّد بنموذج لغوي: تكلفته حقيقية، فلا يُستدعى إلا بطلب صريح من
 *     المستخدم، ويُحجز له من سقف المساحة قبل أي استدعاء (§٤.٤).
 *   - `fitness` حتميّ ومحليّ بلا تكلفة: يُستدعى أثناء الكتابة، فيرى صاحب النشاط
 *     أن وصفه عامٌّ وهو ما زال أمام السؤال — لا في تقرير لا يستطيع تعديله.
 */
class QuestionAssistController extends Controller
{
    public function __construct(
        private readonly AssistComposer $composer,
        private readonly QuestionLocator $locator,
        private readonly AnswerFitnessScorer $fitness,
    ) {}

    /**
     * دليل ومقترحات لسؤال. يعيد كائنًا فارغًا حين لا تتوفر مساعدة، ولا يفشل:
     * المساعدة معونة على السؤال لا شرط للإجابة عنه.
     */
    public function store(Request $request, Project $project): JsonResponse
    {
        abort_unless(ProjectOwnership::owns($request->user(), $project), 404);

        $validated = $request->validate([
            'surface' => ['required', Rule::in(QuestionLocator::surfaces())],
            'question_key' => ['required', 'string', 'max:191'],
            'run_uuid' => ['nullable', 'string', 'max:64'],
            'session_uuid' => ['nullable', 'string', 'max:64'],
        ], [], [
            'surface' => 'موضع السؤال',
            'question_key' => 'مفتاح السؤال',
        ]);

        $question = $this->locate($project, $validated);

        abort_if($question === null, 404);

        $draft = $request->boolean('cached_only')
            ? $this->composer->cached($project, $question)
            : $this->composer->compose($project, $question);

        return response()->json([
            'data' => ($draft ?? AssistDraft::none())->toArray(),
        ], options: JSON_UNESCAPED_UNICODE);
    }

    /**
     * كفاية إجابة كما هي الآن — بلا حفظ وبلا تكلفة.
     *
     * لا يحفظ شيئًا عن قصد: القيمة المقيسة هنا مسوّدة في المتصفح لم تُرسَل بعد،
     * وحفظُ درجةٍ عنها كان سيُنشئ درجةً لإجابة لا وجود لها في الدماغ. الحفظ يجري
     * على مسار الكتابة الفعلي وحده.
     */
    public function fitness(Request $request, Project $project): JsonResponse
    {
        abort_unless(ProjectOwnership::owns($request->user(), $project), 404);

        $validated = $request->validate([
            'field_key' => ['required', 'string', 'max:191'],
            'type' => ['required', 'string', 'max:32'],
            // القيمة قد تصل نصًّا أو مصفوفة (متكرر): كلاهما يُضم إلى نصٍّ واحد.
            'value' => ['nullable'],
        ], [], ['field_key' => 'الحقل', 'type' => 'نوع الإجابة']);

        if (! AnswerFitnessScorer::measures((string) $validated['type'])) {
            return response()->json(['data' => null], options: JSON_UNESCAPED_UNICODE);
        }

        $text = ArabicText::flatten($validated['value'] ?? null);

        if (trim($text) === '') {
            return response()->json(['data' => null], options: JSON_UNESCAPED_UNICODE);
        }

        return response()->json([
            'data' => $this->fitness->evaluate((string) $validated['field_key'], $text)->toArray(),
        ], options: JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function locate(Project $project, array $validated): ?QuestionDescriptor
    {
        $key = (string) $validated['question_key'];

        if ($validated['surface'] === QuestionAssist::SURFACE_TOOL) {
            $run = ToolRun::where('uuid', $validated['run_uuid'])->first();

            return $run !== null && $this->locator->belongsTo($project, $run)
                ? $this->locator->inToolRun($run, $key)
                : null;
        }

        $session = ConsultationSession::where('uuid', $validated['session_uuid'])->first();

        return $session !== null && $this->locator->belongsTo($project, $session)
            ? $this->locator->inConsultation($session, $key)
            : null;
    }
}
