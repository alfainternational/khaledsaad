<?php

declare(strict_types=1);

namespace App\Modules\Insights;

use Illuminate\Http\Request;

/**
 * أحداث القمع — قياس المسار من «بدأ» إلى «وصل إلى تقرير».
 *
 * **السبب في وجودها:** عطل «ستون سؤالًا ثم فشل» كان يجب أن يصرخ في لوحة
 * قبل أن يُكتشف بالمصادفة. كل ما في تقرير التدقيق كان قابلًا للرصد
 * آليًّا: نسبة من يصطدم بالبوابة، ومتوسط رقم السؤال عند الانقطاع، ومعدل
 * التأجيل. لم يكن ينقص جهازُ قياس، بل أن يوصَّل.
 *
 * وبدونها لا نعرف إن كان ما أصلحناه قد نفع: نُصلح بلا مقياس، فنكرّر
 * الخطأ نفسه من الجهة المقابلة.
 *
 * تُبنى على `ConversionRecorder` القائم لا بجانبه: مسارُ تسجيلٍ ثانٍ
 * يعني جدولين وحقيقتين ولوحتين.
 */
final class FunnelRecorder
{
    /** بدء تدفق يستهلك موارد. */
    public const FLOW_STARTED = 'flow_started';

    /** عُرضت البوابة قبل السؤال الأول (INV-4). */
    public const PREFLIGHT_SHOWN = 'preflight_shown';

    /** اصطدم المستخدم بجدار قبل أن يبدأ — يُنبَّه إن ارتفع. */
    public const PREFLIGHT_BLOCKED = 'preflight_blocked';

    /** أُجّل تشغيل لعطلٍ لدينا — يُنبَّه فورًا. */
    public const RUN_DEFERRED = 'run_deferred';

    /** وُلّدت مهام من تقرير — يقيس إقفال الحلقة. */
    public const TASK_MATERIALIZED = 'task_materialized';

    /** تبنّى المستخدم مهمة — المؤشر الحاكم للاحتفاظ. */
    public const TASK_ADOPTED = 'task_adopted';

    public function __construct(private readonly ConversionRecorder $conversions) {}

    /**
     * @param  array<string, mixed>  $meta
     */
    public function record(Request $request, string $name, array $meta = []): void
    {
        $this->conversions->record($request, $name, null, $meta);
    }

    /**
     * البوابة كما رآها المستخدم: ما رآه وما منعه.
     *
     * `outcome` هو الحقل الذي يجيب على «كم واحدًا يصطدم بجدار؟» — وهو
     * السؤال الذي لم يكن أحد يسأله حين وقع العطل.
     */
    public function preflight(Request $request, string $outcome, array $meta = []): void
    {
        $this->record($request, self::PREFLIGHT_SHOWN, ['outcome' => $outcome] + $meta);

        if ($outcome !== 'ready') {
            $this->record($request, self::PREFLIGHT_BLOCKED, ['reason' => $outcome] + $meta);
        }
    }
}
