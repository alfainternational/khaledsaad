<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Modules\Intake\Assist\ProfileQuestions;
use App\Modules\Intake\Fields\FieldDirectory;
use App\Services\Tools\ProjectAnswerMemory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * سدّ فجوات التقرير من التطبيق.
 *
 * التطبيق كان يعرض `assumptions` نصًّا كما كان الويب يفعل — ويقف عندها. وبما
 * أن `ReportPresenter` واحد للسطحين، فإن `open_gaps` وصل التطبيق مع أول تغيير
 * في العارض؛ الناقص كان بابَ الكتابة. وبلا هذا المتحكّم يبقى المستخدم على
 * الجوال يقرأ ما ينقصه ولا يستطيع كتابته، فيصير التكافؤ عرضًا بلا فعل.
 *
 * الحدود هنا نفسها الحدود في الويب حرفيًّا: مفتاح لم يعلنه التقرير لا يُقبل،
 * والحفظ يمرّ بـ`ProjectAnswerMemory` فيُقاس كما يُقاس أي جواب آخر. حدّان
 * مختلفان بين السطحين يعنيان أن أحدهما بابٌ خلفيّ.
 */
class ReportGapController extends Controller
{
    use ResolvesWorkspace;

    public function __construct(
        private readonly FieldDirectory $fields,
        private readonly ProjectAnswerMemory $memory,
    ) {}

    public function index(Request $request, Report $report): JsonResponse
    {
        $this->authorizeReport($request, $report);

        return response()->json([
            'data' => $this->open($report),
            'run_uuid' => $report->toolRun?->uuid,
        ]);
    }

    public function update(Request $request, Report $report): JsonResponse
    {
        $this->authorizeReport($request, $report);

        $open = collect($this->open($report))->pluck('key')->all();

        $payload = collect($request->input('answers', []))
            ->filter(fn ($value, $key) => in_array((string) $key, $open, true))
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->filter(fn ($value) => $value !== null && $value !== '' && $value !== [])
            ->all();

        if ($payload === []) {
            return response()->json([
                'message' => __('اكتب إجابة واحدة على الأقل قبل الحفظ.'),
            ], 422);
        }

        $saved = $this->memory->rememberDeclared($report->project, $payload);

        $report->forceFill([
            'declared_gaps' => collect($report->declared_gaps ?? [])
                ->map(fn (array $gap) => in_array($gap['key'] ?? '', $saved, true)
                    ? [...$gap, 'answered_at' => now()->toIso8601String()]
                    : $gap)
                ->all(),
        ])->save();

        return response()->json([
            'saved' => $saved,
            'remaining' => $this->open($report->refresh()),
            'message' => __('حُفظت :count معلومة. أعد تشغيل التشخيص لتدخل في النتيجة.', ['count' => count($saved)]),
        ]);
    }

    /**
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
                    'options' => array_values($profile['options'] ?? []),
                    'surface' => $profile !== null ? 'profile' : 'tool',
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
