<?php

namespace App\Modules\Intake;

use App\Models\Project;
use App\Modules\Intake\Contracts\SpeechToText;
use App\Modules\Measurement\Exceptions\BudgetExhausted;
use App\Modules\Measurement\QueryBudgetManager;
use Throwable;

/**
 * الاستقبال الصوتي: يتكلّم صاحب النشاط بدل أن يكتب.
 *
 * لماذا يهم؟ لأن أثقل ما في المنتج هو الاستقبال: صاحب مطعم لن يكتب فقرتين عن
 * تموضعه، لكنه يقولهما في عشرين ثانية. الصوت يرفع اكتمال المحاور ١–٦، وهي
 * أكثر ما تنقص فيه التغطية.
 *
 * **النسخ لا يملأ حقلًا مباشرة.** يعيد نصًّا يراجعه صاحبه ثم يعتمده: النسخ
 * العربي يخطئ في الأسماء والأرقام، وحقيقةٌ في الدماغ مصدرها خطأ نسخ أسوأ من
 * فجوة معلنة (§٤.٣). ما يدخل الدماغ يدخل من المسار المعتاد بعد المراجعة.
 *
 * التكلفة تُحجز من سقف المساحة (§٩): النسخ تكلفة متغيرة كالاستطلاع تمامًا.
 */
class VoiceIntake
{
    /**
     * ثوانٍ لكل موضع محجوز.
     *
     * دقيقة واحدة = موضع. تقريب معلن لا خفيّ: الغرض حماية السقف من تسجيل
     * طويل، لا محاسبة دقيقة — تلك تُسجَّل بالتكلفة الفعلية عند التسوية.
     */
    private const SECONDS_PER_PLACE = 60;

    public function __construct(
        private readonly SpeechToText $speech,
        private readonly QueryBudgetManager $budgets,
    ) {}

    /**
     * نسخ تسجيل، بحجز مسبق يتناسب مع طوله.
     *
     * @param  int  $estimatedSeconds  طول التسجيل كما يعلنه العميل، لتقدير الحجز.
     * @param  string|null  $filename  اسم الملف كما أعلنه العميل — منه وحده يُعرف
     *                                 امتداد الصوت، فمسار الرفع المؤقت لا يحمله.
     * @return array{text: string, duration_seconds: float, provider: string}
     *
     * @throws BudgetExhausted
     */
    public function transcribe(Project $project, string $path, int $estimatedSeconds, ?string $filename = null): array
    {
        $reservation = $this->budgets->reserve(
            workspace: $project->workspace,
            queries: max(1, (int) ceil($estimatedSeconds / self::SECONDS_PER_PLACE)),
            purpose: 'voice_intake',
            project: $project,
        );

        try {
            $result = $this->speech->transcribe($path, 'ar', $filename);
        } catch (Throwable $exception) {
            // لم يحصل على نصّ، فلا يُحاسَب على شيء (§١٢).
            $this->budgets->release($reservation);

            throw $exception;
        }

        $this->budgets->settle(
            $reservation,
            costUsd: $result['cost_usd'],

            /*
             * الطول الفعلي لا المقدَّر: تسجيل أعلنه العميل دقيقتين وكان نصف
             * دقيقة يُعيد ما لم يُستهلك — السقف حماية لا حصة تُحرق.
             */
            actualQueries: max(1, (int) ceil($result['duration_seconds'] / self::SECONDS_PER_PLACE)),
        );

        return [
            'text' => $result['text'],
            'duration_seconds' => $result['duration_seconds'],
            'provider' => $this->speech->name(),
        ];
    }
}
