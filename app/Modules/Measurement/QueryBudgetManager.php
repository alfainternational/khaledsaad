<?php

namespace App\Modules\Measurement;

use App\Models\Project;
use App\Models\Workspace;
use App\Modules\Measurement\Exceptions\BudgetExhausted;
use App\Modules\Measurement\Models\QueryBudget;
use App\Modules\Measurement\Models\QueryReservation;
use App\Notifications\QueryBudgetWarningNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * سقف التكلفة التشغيلي: الحجز قبل الطابور، لا المحاسبة بعده (§٩).
 *
 * الترتيب هو كل شيء. المنصة قبل هذا كانت تسجّل `cost_usd` **بعد** الاستدعاء،
 * أي أنها تعرف كم أنفقت ولا تستطيع أن تمنع دولارًا واحدًا. الحجز المسبق يقلب
 * العلاقة: لا مهمة تدخل الطابور إلا وقد ضُمن لها موضع.
 *
 * القفل `lockForUpdate` ليس احتياطًا نظريًّا: عشر مهام متزامنة على مساحة
 * واحدة تقرأ الحدّ نفسه وتظنّ كلٌّ منها أن هناك متسعًا. بلا القفل يمرّ العشرة
 * ويُكتشف التجاوز في فاتورة آخر الشهر.
 *
 * لا اتصال شبكي هنا: هذه الوحدة تقرأ وتكتب قاعدة البيانات فقط، ويفرضه اختبار
 * معماري.
 */
class QueryBudgetManager
{
    /** السقف الافتراضي حين لا يُضبط للمساحة سقف خاص. */
    public const DEFAULT_MONTHLY_LIMIT = 300;

    /**
     * حجز مواضع أو الرفض.
     *
     * يُستدعى **قبل** `dispatch`. استدعاؤه داخل المهمة يعني أن المهمة دخلت
     * الطابور أصلًا، فيصير الرفض تعطيلًا متأخرًا لا منعًا.
     *
     * @throws BudgetExhausted
     */
    public function reserve(
        Workspace $workspace,
        int $queries,
        string $purpose,
        ?Project $project = null,
    ): QueryReservation {
        if ($queries < 1) {
            throw new \InvalidArgumentException('الحجز لا يكون بصفر استعلام.');
        }

        $reservation = DB::transaction(function () use ($workspace, $queries, $purpose, $project): QueryReservation {
            $budget = $this->lockedBudgetFor($workspace);

            if ($budget->remaining() < $queries) {
                /*
                 * الرفض جزئي أيضًا: طلب خمسة ومتبقٍّ ثلاثة يُرفض كاملًا ولا
                 * يُنفَّذ بثلاثة. استطلاع بثلاث محاولات بدل خمس يُنتج رقمًا
                 * بمقام مختلف عن المعلن، وهو أسوأ من ألّا يُنتج شيئًا (§٤.٢).
                 */
                throw new BudgetExhausted(
                    $budget->monthly_limit,
                    $budget->committed(),
                    $queries,
                );
            }

            $budget->increment('reserved', $queries);

            return $budget->reservations()->create([
                'project_id' => $project?->id,
                'purpose' => $purpose,
                'queries' => $queries,
                'status' => QueryReservation::STATUS_HELD,
            ]);
        });

        /*
         * التنبيه **بعد** إغلاق المعاملة لا داخلها: إرسال إشعار أثرٌ جانبي،
         * وفشله داخل المعاملة كان سيُرجع حجزًا صالحًا فيُمنع استعلام لا سبب
         * لمنعه. وهو لا يرمي: من بلغ ٨٠٪ يستمر عمله حتى لو تعذّر إشعاره.
         */
        $this->warnIfDue($workspace);

        return $reservation;
    }

    /**
     * تنبيه صاحب المساحة عند بلوغ العتبة، مرة واحدة في الشهر (§٤.٤).
     *
     * التوقف عند ١٠٠٪ وحده يجعل الحدّ يُكتشف بالاصطدام به وسط عمل. التنبيه
     * هو ما يمنح قرارًا قبل المنع — وبلا `warned_at` يتكرر مع كل حجز تالٍ
     * فيُقرأ ضجيجًا ويُتجاهَل معه التوقف نفسه.
     */
    private function warnIfDue(Workspace $workspace): void
    {
        $budget = $this->budgetFor($workspace);

        /*
         * `markWarned` هو من يحسم لا `shouldWarn`: الفحص ثم الوسم في خطوتين
         * يمرّان معًا لحجزين متزامنين عبرا العتبة في اللحظة نفسها، فيصل
         * التنبيه مرتين. الوسم المشروط يجعل الفائز واحدًا.
         */
        if (! $budget->shouldWarn() || ! $this->markWarned($budget)) {
            return;
        }

        try {
            $workspace->owner?->notify(new QueryBudgetWarningNotification(
                committed: $budget->committed(),
                limit: $budget->monthly_limit,
                period: $budget->period,
            ));
        } catch (Throwable $exception) {
            // الوسم تمّ قبل الإرسال عمدًا: إشعار يفشل ثم يُعاد كل حجز أسوأ
            // من إشعار ضاع مرة.
            Log::warning('تعذّر إرسال تنبيه سقف الاستعلامات.', [
                'workspace_id' => $workspace->id,
                'reason' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * تثبيت الحجز بعد التنفيذ، بتكلفته الفعلية.
     *
     * `$actualQueries` يقل عن المحجوز حين يتوقف الاستطلاع مبكرًا. الفرق يعود
     * إلى الميزانية بدل أن يُحرَق: السقف حماية من الإنفاق لا حصة تُستهلك.
     */
    public function settle(QueryReservation $reservation, float $costUsd, ?int $actualQueries = null): QueryReservation
    {
        return DB::transaction(function () use ($reservation, $costUsd, $actualQueries): QueryReservation {
            $reservation = $reservation->newQuery()->lockForUpdate()->findOrFail($reservation->id);

            if (! $reservation->isOpen()) {
                return $reservation;
            }

            $used = min($actualQueries ?? $reservation->queries, $reservation->queries);
            $budget = $reservation->budget()->lockForUpdate()->firstOrFail();

            $budget->decrement('reserved', $reservation->queries);
            $budget->increment('consumed', $used);
            $budget->increment('cost_usd', $costUsd);

            $reservation->update([
                'status' => QueryReservation::STATUS_CONSUMED,
                'queries' => $used,
                'cost_usd' => $costUsd,
                'settled_at' => now(),
            ]);

            return $reservation;
        });
    }

    /**
     * إعادة المواضع: فشل المزوّد أو أُلغيت المهمة.
     *
     * فشل المزوّد لا يُحاسَب عليه صاحب المساحة — لم يحصل على شيء. ما يُسجَّل
     * هو التكلفة الفعلية إن كان المزوّد قد حصّلها رغم الفشل (§١٢).
     */
    public function release(QueryReservation $reservation, float $costUsd = 0.0): QueryReservation
    {
        return DB::transaction(function () use ($reservation, $costUsd): QueryReservation {
            $reservation = $reservation->newQuery()->lockForUpdate()->findOrFail($reservation->id);

            if (! $reservation->isOpen()) {
                return $reservation;
            }

            $budget = $reservation->budget()->lockForUpdate()->firstOrFail();

            $budget->decrement('reserved', $reservation->queries);
            $budget->increment('cost_usd', $costUsd);

            $reservation->update([
                'status' => QueryReservation::STATUS_RELEASED,
                'cost_usd' => $costUsd,
                'settled_at' => now(),
            ]);

            return $reservation;
        });
    }

    /**
     * ميزانية الشهر الجاري، تُنشأ عند أول استعمال.
     */
    public function budgetFor(Workspace $workspace, ?string $period = null): QueryBudget
    {
        return QueryBudget::firstOrCreate(
            ['workspace_id' => $workspace->id, 'period' => $period ?? $this->currentPeriod()],
            ['monthly_limit' => $this->limitFor($workspace)],
        );
    }

    /**
     * حجز حقّ التنبيه لهذا الشهر، مرة واحدة لا أكثر.
     *
     * الوسم مشروط بـ `whereNull` لا `save()` مجرّدًا: من يكتب أولًا يُنبِّه،
     * ومن يليه ينصرف. يُستدعى بعد إغلاق المعاملة لا داخلها — التنبيه أثر
     * جانبي، وإقحامه فيها يجعل فشل الإشعار يُرجع حجزًا صالحًا.
     *
     * @return bool هل صار هذا المستدعي هو المسؤول عن الإرسال؟
     */
    public function markWarned(QueryBudget $budget): bool
    {
        $now = now();

        $claimed = QueryBudget::query()
            ->whereKey($budget->getKey())
            ->whereNull('warned_at')
            ->update(['warned_at' => $now]);

        if ($claimed === 0) {
            return false;
        }

        // مزامنة الأصل حتى لا يُعدّ الحقل «متغيّرًا» فيُكتب مرة ثانية بلا شرط.
        $budget->setAttribute('warned_at', $now)->syncOriginalAttribute('warned_at');

        return true;
    }

    private function lockedBudgetFor(Workspace $workspace): QueryBudget
    {
        $this->budgetFor($workspace);

        return QueryBudget::query()
            ->where('workspace_id', $workspace->id)
            ->where('period', $this->currentPeriod())
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * السقف من إعدادات المساحة، وإلا الافتراضي.
     *
     * السقف على `Workspace` لا `Project`: وكالة بعشرة مشاريع كانت ستأخذ عشرة
     * أضعاف السقف باشتراك واحد (§٩).
     */
    private function limitFor(Workspace $workspace): int
    {
        return (int) ($workspace->monthly_query_limit ?? config(
            'growth.query_budget_default',
            self::DEFAULT_MONTHLY_LIMIT,
        ));
    }

    private function currentPeriod(): string
    {
        return now()->format('Y-m');
    }
}
