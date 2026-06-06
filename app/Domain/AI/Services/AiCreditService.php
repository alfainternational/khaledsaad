<?php

namespace App\Domain\AI\Services;

use App\Domain\Account\Models\Account;
use App\Domain\AI\Models\AICreditsLedger;

/**
 * مصدر الحقيقة الوحيد لرصيد الـ AI credits على مستوى الحساب.
 * الرصيد = مجموع الـ deltas في ai_credits_ledger (منح موجبة، استهلاك سالب).
 *
 * القاعدة المعمارية (CLAUDE.md §31): كل توليد LLM يستهلك credits ويُسجَّل.
 * التطبيق (الحظر) اختياري عبر services.ai.enforce_credits، لكن التسجيل دائم.
 */
class AiCreditService
{
    public function balanceFor(Account $account): int
    {
        return (int) AICreditsLedger::query()
            ->where('account_id', $account->getKey())
            ->sum('delta');
    }

    public function hasBalance(Account $account, int $needed = 1): bool
    {
        if (! (bool) config('services.ai.enforce_credits', false)) {
            return true;
        }

        return $this->balanceFor($account) >= max(1, $needed);
    }

    /**
     * يسجّل استهلاك credits (delta سالب). يُستدعى بعد نجاح التوليد فقط.
     */
    public function consume(Account $account, int $amount, string $reason, ?string $refId = null): AICreditsLedger
    {
        $amount = max(1, abs($amount));

        return AICreditsLedger::query()->create([
            'account_id' => $account->getKey(),
            'delta' => -$amount,
            'reason' => $reason,
            'ref_id' => $refId,
        ]);
    }
}
