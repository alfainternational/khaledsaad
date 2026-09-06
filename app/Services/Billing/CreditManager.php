<?php

namespace App\Services\Billing;

use App\Exceptions\BillingLimitException;
use App\Models\CreditTransaction;
use App\Models\CreditWallet;
use App\Models\ToolRun;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

/**
 * إدارة الأرصدة بنمط حجز ثم خصم.
 *
 * قواعد العمل:
 * - BR-004: يُحجز الرصيد عند بدء التشغيل ويُخصم عند النجاح.
 * - BR-011: الفشل التقني لا يستهلك رصيدًا — الحجز يُلغى بالكامل.
 * - INV-9: كل حركة تحمل مفتاح تكرار، فتكرارها لا يكرّر أثرها.
 *
 * كل عملية تمر بقفل صف المحفظة (lockForUpdate) حتى لا يتسبب تشغيلان
 * متزامنان في خصم مزدوج أو رصيد سالب. والقفل وحده لا يكفي: طلبان
 * متتاليان (لا متزامنان) يمرّ كلٌّ منهما بالقفل بنجاح ثم يخصمان مرتين.
 * لذلك القفل يحمي من التزامن، والمفتاح الفريد يحمي من التكرار.
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
     *
     * الحجز مفتاحه التشغيل نفسه: تشغيلٌ واحد لا يُحجز له مرتين مهما
     * تكرّر النداء — والنداء يتكرّر فعلًا مع إعادة المحاولة من الطابور.
     */
    public function hold(ToolRun $run, int $credits, ?string $key = null): CreditTransaction
    {
        $key ??= "hold:run:{$run->id}";

        if ($credits <= 0) {
            return $this->existing($key)
                ?? $this->record($run, self::wallet($run), CreditTransaction::TYPE_HOLD, 0, 'أداة مجانية', $key);
        }

        return DB::transaction(function () use ($run, $credits, $key): CreditTransaction {
            $wallet = $this->lockedWallet($run);

            // الفحص داخل القفل: بين قراءةٍ خارجه وكتابةٍ داخله تتّسع
            // نافذةٌ يمرّ منها نداءٌ ثانٍ.
            if ($existing = $this->existing($key)) {
                return $existing;
            }

            if ($wallet->balance < $credits) {
                throw BillingLimitException::credits($credits, $wallet->balance);
            }

            $wallet->decrement('balance', $credits);

            return $this->write($wallet, $run, CreditTransaction::TYPE_HOLD, -$credits, 'حجز لتشغيل أداة', $key);
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
            $this->write(
                $wallet, $run, CreditTransaction::TYPE_CHARGE, 0,
                'خصم نهائي لتشغيل ناجح', "charge:run:{$run->id}",
            );
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

            $this->write(
                $wallet, $run, CreditTransaction::TYPE_REFUND, $held,
                'استرداد لفشل تقني', "refund:run:{$run->id}",
            );
        });
    }

    /**
     * منح رصيد (تجديد اشتراك، شراء حزمة، هدية).
     *
     * المفتاح هنا **مسؤولية المنادي**، ولا افتراضي له: إشعار بوابة الدفع
     * يصل مرتين بحكم تصميمه، ومنحتان بمفتاح مشتقّ من الوقت تمرّان معًا.
     * المنادي وحده يعرف ما الذي يجعل هذه المنحة *هي* لا غيرها — رقم
     * الدفعة، أو الشهر، أو معرّف الطلب.
     */
    public function grant(Workspace $workspace, int $credits, string $reason, ?string $key = null): CreditTransaction
    {
        return DB::transaction(function () use ($workspace, $credits, $reason, $key): CreditTransaction {
            $wallet = CreditWallet::where('workspace_id', $workspace->id)->lockForUpdate()->firstOr(
                fn () => $this->walletFor($workspace),
            );

            if ($key !== null && $existing = $this->existing($key)) {
                return $existing;
            }

            $wallet->increment('balance', $credits);

            return $this->write($wallet, null, CreditTransaction::TYPE_GRANT, $credits, $reason, $key);
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

    private function existing(string $key): ?CreditTransaction
    {
        return CreditTransaction::where('idempotency_key', $key)->first();
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

    private function record(ToolRun $run, CreditWallet $wallet, string $type, int $amount, string $reason, ?string $key = null): CreditTransaction
    {
        return $this->write($wallet, $run, $type, $amount, $reason, $key);
    }

    private function write(CreditWallet $wallet, ?ToolRun $run, string $type, int $amount, string $reason, ?string $key = null): CreditTransaction
    {
        return CreditTransaction::create([
            'credit_wallet_id' => $wallet->id,
            'tool_run_id' => $run?->id,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $wallet->fresh()->balance,
            'reason' => $reason,
            'idempotency_key' => $key,
        ]);
    }
}
