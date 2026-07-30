<?php

namespace App\Services\Billing;

use App\Models\CreditTransaction;
use App\Models\CreditWallet;
use App\Models\ToolRun;
use App\Exceptions\BillingLimitException;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

/**
 * إدارة الأرصدة بنمط حجز ثم خصم.
 *
 * قواعد العمل:
 * - BR-004: يُحجز الرصيد عند بدء التشغيل ويُخصم عند النجاح.
 * - BR-011: الفشل التقني لا يستهلك رصيدًا — الحجز يُلغى بالكامل.
 *
 * كل عملية تمر بقفل صف المحفظة (lockForUpdate) حتى لا يتسبب تشغيلان
 * متزامنان في خصم مزدوج أو رصيد سالب.
 */
class CreditManager
{
    public function walletFor(Workspace $workspace): CreditWallet
    {
        return CreditWallet::firstOrCreate(
            ['workspace_id' => $workspace->id],
            ['balance' => 0],
        );
    }

    /**
     * حجز رصيد قبل التشغيل. يرمي استثناءً إن لم يكفِ الرصيد.
     */
    public function hold(ToolRun $run, int $credits): CreditTransaction
    {
        if ($credits <= 0) {
            return $this->record($run, self::wallet($run), CreditTransaction::TYPE_HOLD, 0, 'أداة مجانية');
        }

        return DB::transaction(function () use ($run, $credits): CreditTransaction {
            $wallet = $this->lockedWallet($run);

            if ($wallet->balance < $credits) {
                throw BillingLimitException::credits($credits, $wallet->balance);
            }

            $wallet->decrement('balance', $credits);

            return $this->write($wallet, $run, CreditTransaction::TYPE_HOLD, -$credits, 'حجز لتشغيل أداة');
        });
    }

    /**
     * تثبيت الخصم بعد نجاح التشغيل. الحجز صار خصمًا نهائيًا — لا تغيير في الرصيد.
     */
    public function charge(ToolRun $run): void
    {
        DB::transaction(function () use ($run): void {
            $held = $this->heldAmount($run);

            if ($held === 0) {
                return;
            }

            $wallet = $this->lockedWallet($run);

            // الرصيد خُصم عند الحجز؛ الخصم هنا سجلّي فقط لإغلاق الدورة.
            $this->write($wallet, $run, CreditTransaction::TYPE_CHARGE, 0, 'خصم نهائي لتشغيل ناجح');
        });
    }

    /**
     * استرداد الحجز عند الفشل التقني — BR-011.
     */
    public function refund(ToolRun $run): void
    {
        DB::transaction(function () use ($run): void {
            $held = $this->heldAmount($run);

            if ($held === 0) {
                return;
            }

            $wallet = $this->lockedWallet($run);
            $wallet->increment('balance', $held);

            $this->write($wallet, $run, CreditTransaction::TYPE_REFUND, $held, 'استرداد لفشل تقني');
        });
    }

    /**
     * منح رصيد (تجديد اشتراك، شراء حزمة، هدية).
     */
    public function grant(Workspace $workspace, int $credits, string $reason): CreditTransaction
    {
        return DB::transaction(function () use ($workspace, $credits, $reason): CreditTransaction {
            $wallet = CreditWallet::where('workspace_id', $workspace->id)->lockForUpdate()->firstOr(
                fn () => $this->walletFor($workspace),
            );

            $wallet->increment('balance', $credits);

            return $this->write($wallet, null, CreditTransaction::TYPE_GRANT, $credits, $reason);
        });
    }

    /**
     * صافي ما هو محجوز لهذا التشغيل ولم يُثبَّت أو يُسترد بعد.
     */
    private function heldAmount(ToolRun $run): int
    {
        $transactions = CreditTransaction::where('tool_run_id', $run->id)->get();

        // إن كان قد ثُبّت أو استُرد سابقًا، فلا يوجد حجز قائم.
        $settled = $transactions->whereIn('type', [
            CreditTransaction::TYPE_CHARGE,
            CreditTransaction::TYPE_REFUND,
        ])->isNotEmpty();

        if ($settled) {
            return 0;
        }

        return abs((int) $transactions->where('type', CreditTransaction::TYPE_HOLD)->sum('amount'));
    }

    private function lockedWallet(ToolRun $run): CreditWallet
    {
        return CreditWallet::where('workspace_id', $run->project->workspace_id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private static function wallet(ToolRun $run): CreditWallet
    {
        return CreditWallet::firstOrCreate(
            ['workspace_id' => $run->project->workspace_id],
            ['balance' => 0],
        );
    }

    private function record(ToolRun $run, CreditWallet $wallet, string $type, int $amount, string $reason): CreditTransaction
    {
        return $this->write($wallet, $run, $type, $amount, $reason);
    }

    private function write(CreditWallet $wallet, ?ToolRun $run, string $type, int $amount, string $reason): CreditTransaction
    {
        return CreditTransaction::create([
            'credit_wallet_id' => $wallet->id,
            'tool_run_id' => $run?->id,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $wallet->fresh()->balance,
            'reason' => $reason,
        ]);
    }
}
