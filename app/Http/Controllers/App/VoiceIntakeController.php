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
            /*
             * 20MB: خمس دقائق بترميز معقول. الحد يمنع رفعًا يُسقط الطلب.
             *
             * القائمة تشمل `x-m4a` و`aac` لأنهما ما يُنتجه مسجّل التطبيق فعلًا
             * (AAC داخل حاوية m4a)، وPHP يصنّفهما بأسماء مختلفة حسب النظام.
             * قصرُها على `audio/mp4` كان سيرفض تسجيلات صحيحة برسالة تحقّق
             * غامضة يستحيل على المستخدم فهمها.
             */
            /*
             * القائمة تضم `video/webm` و`video/mp4` وليس هذا خطأ نسخ: الحاوية
             * الواحدة تُصنَّف بأسماء مختلفة حسب النظام، وتسجيلٌ صوتي بحت داخل
             * حاوية WebM يقرؤه finfo على أنه `video/webm`. كان الحدّ يرفض
             * تسجيلات صحيحة برسالة تحقّق غامضة يستحيل على المستخدم فهمها.
             */
            'audio' => [
                'required', 'file', 'max:20480',
                'mimetypes:audio/mpeg,audio/mp3,audio/mpga,audio/mp4,audio/m4a,audio/x-m4a,audio/aac,'
                .'audio/x-hx-aac-adts,audio/wav,audio/wave,audio/x-wav,audio/vnd.wave,audio/webm,audio/ogg,'
                .'audio/flac,audio/x-flac,video/mp4,video/webm,video/ogg,application/ogg',
            ],
            'seconds' => ['required', 'integer', 'min:1', 'max:'.self::MAX_SECONDS],
        ], [], ['audio' => __('التسجيل'), 'seconds' => __('مدة التسجيل')]);

        try {
            $result = $this->voice->transcribe(
                $project,
                $request->file('audio')->getRealPath(),
                (int) $validated['seconds'],
                /*
                 * الاسم المعلن يُمرَّر ليُستخرج منه الامتداد: مسار الرفع المؤقت
                 * اسمه `phpXXXX.tmp`، ومزوّد النسخ يرفض ما لا امتداد صوتي له.
                 */
                $request->file('audio')->getClientOriginalName(),
            );
        } catch (BudgetExhausted $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => __('تعذّر نسخ التسجيل. حاول مرة أخرى أو اكتب إجابتك.'),
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
