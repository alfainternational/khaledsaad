<?php

declare(strict_types=1);

namespace App\Support\Preflight;

use App\Support\Presentation\Num;

/**
 * نتيجة البوابة على حزمة أدوات — لا على أداة واحدة.
 *
 * الفرق الجوهري عن `PreflightResult`: هنا يوجد **نجاح جزئي**. من يكفيه
 * سبعٌ من إحدى عشرة أداة ليس ممنوعًا، بل يبدأ بالسبع الأعلى أثرًا. منعُه
 * كليًّا كان سيحرمه القيمة كلها لأنه لا يملك ثمن آخر أداة.
 */
final class BundlePreflightResult
{
    public function __construct(
        public readonly PreflightOutcome $outcome,
        public readonly int $toolsTotal = 0,
        public readonly int $toolsAffordable = 0,
        public readonly int $cost = 0,
        public readonly int $affordableCost = 0,
        public readonly int $balance = 0,
    ) {}

    public function isReady(): bool
    {
        return $this->outcome === PreflightOutcome::Ready;
    }

    public function isOurFault(): bool
    {
        return $this->outcome === PreflightOutcome::ProviderUnavailable;
    }

    /** يستطيع البدء بشيء — كليًّا أو جزئيًّا. */
    public function canStart(): bool
    {
        return ! $this->isOurFault() && $this->toolsAffordable > 0;
    }

    public function isPartial(): bool
    {
        return $this->outcome === PreflightOutcome::PartialBudget;
    }

    /**
     * السطر قبل الزر. يذكر الأدوات والتكلفة والرصيد معًا — والثلاثة معًا
     * هي ما كان غائبًا حين بدأ مستخدمٌ ستين سؤالًا لا يستطيع إنهاءها.
     */
    public function headline(): string
    {
        if ($this->isOurFault()) {
            return __('التشغيل متوقف مؤقتًا لأسباب لدينا — لا تبدأ الآن كي لا يضيع وقتك.');
        }

        if ($this->isPartial()) {
            return __('رصيدك يكفي :affordable من :total — نبدأ بالأعلى أثرًا. (رصيدك: :balance)', [
                'affordable' => $this->tools($this->toolsAffordable),
                'total' => $this->tools($this->toolsTotal),
                'balance' => Num::credits($this->balance),
            ]);
        }

        if ($this->outcome === PreflightOutcome::InsufficientCredits) {
            return __('الاستشارة الشاملة :tools وتكلّف :cost، ورصيدك الحالي :balance.', [
                'tools' => $this->tools($this->toolsTotal),
                'cost' => Num::credits($this->cost),
                'balance' => Num::int($this->balance),
            ]);
        }

        return __('الاستشارة الشاملة :tools · تكلّف :cost (رصيدك: :balance)', [
            'tools' => $this->tools($this->toolsTotal),
            'cost' => Num::credits($this->cost),
            'balance' => Num::credits($this->balance),
        ]);
    }

    public function shortfall(): int
    {
        return max(0, $this->cost - $this->balance);
    }

    private function tools(int $count): string
    {
        return trans_choice(
            '{0} بلا أدوات|{1} أداة واحدة|{2} أداتان|[3,10] :count أدوات|[11,*] :count أداة',
            $count,
            ['count' => Num::int($count)],
        );
    }
}
