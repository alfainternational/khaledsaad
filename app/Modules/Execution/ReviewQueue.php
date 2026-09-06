<?php

declare(strict_types=1);

namespace App\Modules\Execution;

use App\Models\ToolRun;
use Illuminate\Support\Carbon;

/**
 * طابور المراجعة البشرية ومدّته المعلنة.
 *
 * الوعد بمراجعة بشرية لا يُعرض بلا طابور وSLA خلفه. والسبب ليس تنظيميًّا:
 * من وُعِد بيومين ثم انتظر أسبوعًا يقرأ التأخير خذلانًا، ومن لم يُوعَد
 * بشيء ينتظر راضيًا. الوعد المكسور أسوأ من غياب الوعد.
 *
 * ولذلك للطابور **سقف**: عند بلوغه يتوقف الاستقبال ويُعلَن ذلك صراحةً،
 * بدل أن يُقبل الطلب المئة بالوعد نفسه الذي أُعطي للأول.
 */
final class ReviewQueue
{
    public function slaHours(): int
    {
        return max(1, (int) config('review.sla_hours', 48));
    }

    public function maxOpen(): int
    {
        return (int) config('review.max_open', 20);
    }

    /** الطلبات التي لم تُسلَّم بعد. */
    public function openCount(): int
    {
        return ToolRun::where('delivery_mode', 'manual')
            ->whereNotIn('status', [ToolRun::STATUS_COMPLETED, ToolRun::STATUS_PARTIAL])
            ->count();
    }

    /**
     * هل يُقبل طلب جديد؟
     *
     * سقفٌ صفر يعني بلا سقف — والخلط بينه وبين «لا تقبل شيئًا» يوقف
     * الميزة كلها بإعدادٍ يبدو بريئًا.
     */
    public function isAcceptingRequests(): bool
    {
        $max = $this->maxOpen();

        return $max <= 0 || $this->openCount() < $max;
    }

    /** موعد التسليم الموعود لطلبٍ يُقدَّم الآن. */
    public function promisedAt(?Carbon $from = null): Carbon
    {
        return ($from ?? now())->copy()->addHours($this->slaHours());
    }

    /**
     * الطلبات التي تجاوزت مدّتها المعلنة — أهم رقم في هذه الميزة.
     */
    public function breachedCount(): int
    {
        return ToolRun::where('delivery_mode', 'manual')
            ->whereNotIn('status', [ToolRun::STATUS_COMPLETED, ToolRun::STATUS_PARTIAL])
            ->where('updated_at', '<=', now()->subHours($this->slaHours()))
            ->count();
    }

    /**
     * حالة الطابور كما تعرضها اللوحة.
     *
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $open = $this->openCount();
        $max = $this->maxOpen();

        return [
            'open' => $open,
            'max' => $max,
            'breached' => $this->breachedCount(),
            'sla_hours' => $this->slaHours(),
            'accepting' => $this->isAcceptingRequests(),
            // التحذير قبل الامتلاء لا بعده: بعد الامتلاء لم يعد تحذيرًا.
            'crowded' => $max > 0 && $open >= $max * (float) config('review.warn_ratio', 0.8),
        ];
    }
}
