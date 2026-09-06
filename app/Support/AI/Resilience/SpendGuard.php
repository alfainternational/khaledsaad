<?php

declare(strict_types=1);

namespace App\Support\AI\Resilience;

use App\Models\AiUsageRecord;
use Illuminate\Support\Facades\Cache;

/**
 * سقف الإنفاق اليومي على مزوّدات الذكاء.
 *
 * العطل الذي يسدّه: لم يكن شيء يمنع يومًا واحدًا من استهلاك ميزانية شهر —
 * لا حلقة مفرغة في إعادة المحاولة، ولا حساب مُساء استعماله، ولا خطأ في
 * التسعير. والاكتشاف كان يأتي من فاتورة المزوّد بعد وقوعه.
 *
 * القراءة مخبّأة لدقيقة: جمعُ إنفاق اليوم قبل كل استدعاء يضيف استعلامًا
 * على كل مرحلة من عشر مراحل في كل تشغيل، بلا أن يتغير الجواب بينها.
 */
final class SpendGuard
{
    public function cap(): float
    {
        return (float) config('ai.daily_spend_cap_usd', 0);
    }

    public function spentToday(): float
    {
        return (float) Cache::remember(
            'ai:spend:'.now()->toDateString(),
            60,
            fn () => (float) AiUsageRecord::whereDate('created_at', now()->toDateString())->sum('cost_usd'),
        );
    }

    /**
     * صفر يعني «بلا سقف» لا «لا تنفق شيئًا» — والفرق بينهما توقّفُ المنصة.
     */
    public function hasCapacity(): bool
    {
        $cap = $this->cap();

        return $cap <= 0 || $this->spentToday() < $cap;
    }

    /**
     * النسبة المستهلكة، لعرضها في اللوحة وللتنبيه قبل البلوغ لا بعده.
     */
    public function ratio(): ?float
    {
        $cap = $this->cap();

        return $cap > 0 ? min(1.0, $this->spentToday() / $cap) : null;
    }

    /** يُنادى بعد كل كتابة استهلاك حتى لا يتأخر السقف دقيقةً كاملة. */
    public function flush(): void
    {
        Cache::forget('ai:spend:'.now()->toDateString());
    }
}
