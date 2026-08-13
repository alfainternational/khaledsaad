<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Modules\Intake\Assist\ProfileQuestions;
use App\Modules\Intake\Fields\FieldDirectory;
use App\Services\Tools\ProjectAnswerMemory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * سدّ الفجوات التي أعلنها تقرير.
 *
 * **سبب وجوده:** التقرير كان يقول «معلومات تحتاج إلى تأكيد منك» ويعرضها
 * قائمةَ نقاطٍ صمّاء. لا زر ولا رابط. فالمستخدم يقرأ أن تشخيصه ناقص، ويعرف
 * أن النقص منه، ولا يجد أين يكتب. §٤.٣ توجب إعلان الفجوة، وإعلانٌ بلا باب
 * ليس التزامًا بها بل اعتذارٌ عنها.
 *
 * والشاشة لا تُعيد بناء ما هو موجود (§١٥): الأسئلة من `FieldDirectory`،
 * والاقتراح من `assist`، والقياس من `answer-fitness`، والحفظ من
 * `ProjectAnswerMemory`. الجديد هنا وصلةٌ بينها لا آلة سادسة.
 */
class ReportGapController extends Controller
{
    use ResolvesWorkspace;

    public function __construct(
        private readonly FieldDirectory $fields,
        private readonly ProjectAnswerMemory $memory,
    ) {}

    public function edit(Request $request, Report $report): View|RedirectResponse
    {
        $this->authorizeReport($request, $report);

        $gaps = $this->open($report);

        if ($gaps === []) {
            return redirect()->route('app.reports.show', $report)
                ->with('status', __('لا توجد معلومات ناقصة في هذا التقرير.'));
        }

        return view('app.reports.gaps', [
            'report' => $report,
            'project' => $report->project,
            'gaps' => $gaps,
            // المساعدة على سؤال أداة تحتاج تشغيلها لتعرف أي صياغة يقصد
            // المستخدم؛ التقرير يحمل تشغيله فيُمرَّر معه.
            'runUuid' => $report->toolRun?->uuid,
        ]);
    }

    public function update(Request $request, Report $report): RedirectResponse
    {
        $this->authorizeReport($request, $report);

        $open = collect($this->open($report))->pluck('key')->all();

        /*
         * لا يُقبل إلا مفتاح أعلنه هذا التقرير فجوةً.
         *
         * النموذج مفتوح لمن يعدّل الطلب، ومن دون هذا الحدّ يصير بابًا يكتب
         * أي حقيقة في الدماغ بمفتاح يختاره — بلا سؤال يُعرض ولا كفاية تُقاس
         * على نوعه الصحيح.
         */
        $payload = collect($request->input('answers', []))
            ->filter(fn ($value, $key) => in_array((string) $key, $open, true))
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->filter(fn ($value) => $value !== null && $value !== '' && $value !== [])
            ->all();

        if ($payload === []) {
            return back()->withErrors([
                'answers' => __('اكتب إجابة واحدة على الأقل قبل الحفظ.'),
            ]);
        }

        $saved = $this->memory->rememberDeclared($report->project, $payload);

        /*
         * الفجوة المسدودة تُشطب من التقرير ولا تُحذف من تاريخه: التقرير
         * صدر بها، وإخفاؤها بأثر رجعيّ يجعل نسخته المطبوعة تخالف نسخته على
         * الشاشة. تُعلَّم `answered_at` فتظهر مسدودة ويبقى أنها كانت.
         */
        $report->forceFill([
            'declared_gaps' => collect($report->declared_gaps ?? [])
                ->map(fn (array $gap) => in_array($gap['key'] ?? '', $saved, true)
                    ? [...$gap, 'answered_at' => now()->toIso8601String()]
                    : $gap)
                ->all(),
        ])->save();

        return redirect()->route('app.reports.show', $report)->with('status', __(
            'حُفظت :count معلومة. أعد تشغيل التشخيص لتدخل في النتيجة.',
            ['count' => count($saved)],
        ));
    }

    /**
     * الفجوات التي لم تُسدّ بعد، مع سؤال كل واحدة كما يُعرض.
     *
     * @return array<int, array<string, mixed>>
     */
    private function open(Report $report): array
    {
        return collect($report->declared_gaps ?? [])
            ->filter(fn ($gap) => is_array($gap) && ($gap['answered_at'] ?? null) === null)
            ->map(function (array $gap): ?array {
                $key = (string) ($gap['key'] ?? '');
                $described = $this->fields->describe($key);

                if ($described === null) {
                    return null;
                }

                $profile = ProfileQuestions::find($key);

                return [
                    ...$described,
                    'why' => $gap['why'] ?? null,
                    'type' => $profile['type'] ?? 'textarea',
                    'options' => $profile['options'] ?? [],
                    // سطح المساعدة يتبع مصدر الحقل: سؤال الملف يُعان بلا
                    // تشغيل، وسؤال الأداة يحتاج تشغيله ليُعرف أيّ صياغة.
                    'surface' => $profile !== null ? 'profile' : 'tool',
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
