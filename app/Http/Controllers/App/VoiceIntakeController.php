<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Modules\Intake\VoiceIntake;
use App\Modules\Measurement\Exceptions\BudgetExhausted;
use App\Policies\ProjectOwnership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * رفع تسجيل صوتي وإعادة نصّه للمراجعة.
 *
 * **يعيد نصًّا ولا يحفظ شيئًا.** النسخ العربي يخطئ في الأسماء والأرقام،
 * وحقيقةٌ في الدماغ مصدرها خطأ نسخ أسوأ من فجوة معلنة. صاحب النشاط يراجع
 * النص ثم يعتمده من المسار المعتاد.
 */
class VoiceIntakeController extends Controller
{
    /** ثوانٍ. تسجيل أطول يُقطَّع من العميل: النسخة الطويلة تكلفة بلا فائدة. */
    private const MAX_SECONDS = 300;

    public function __construct(private readonly VoiceIntake $voice) {}

    public function store(Request $request, Project $project): JsonResponse
    {
        abort_unless(ProjectOwnership::owns($request->user(), $project), 404);

        $validated = $request->validate([
            // 20MB: خمس دقائق بترميز معقول. الحد يمنع رفعًا يُسقط الطلب.
            'audio' => ['required', 'file', 'mimetypes:audio/mpeg,audio/mp4,audio/wav,audio/webm,audio/ogg', 'max:20480'],
            'seconds' => ['required', 'integer', 'min:1', 'max:'.self::MAX_SECONDS],
        ], [], ['audio' => 'التسجيل', 'seconds' => 'مدة التسجيل']);

        try {
            $result = $this->voice->transcribe(
                $project,
                $request->file('audio')->getRealPath(),
                (int) $validated['seconds'],
            );
        } catch (BudgetExhausted $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => 'تعذّر نسخ التسجيل. حاول مرة أخرى أو اكتب إجابتك.',
            ], 422);
        }

        return response()->json([
            'data' => [
                'text' => $result['text'],
                'duration_seconds' => $result['duration_seconds'],

                /*
                 * الوسم يسافر مع النص: ما يُكتب من نسخ صوتي يُراجَع قبل
                 * اعتماده، والواجهة تحتاج أن تعرف أنه ليس كتابة مباشرة.
                 */
                'needs_review' => true,
            ],
        ], options: JSON_UNESCAPED_UNICODE);
    }
}
