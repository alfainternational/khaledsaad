<?php

declare(strict_types=1);

namespace App\Support\Preflight;

use App\Models\Project;
use App\Models\Tool;
use App\Models\Workspace;
use App\Services\Billing\CreditManager;
use App\Support\AI\Resilience\FallbackChainGateway;
use App\Support\AI\Resilience\SpendGuard;

/**
 * البوابة قبل العمل (INV-4).
 *
 * العطل الذي عالجته: ستون سؤالًا ثم شاشة «تعذّر التحليل». التكلفة والقيود
 * كانت تُفحص بعد آخر سؤال، فبذل المستخدم أقصى مجهوده ثم اصطدم بجدار كان
 * قائمًا قبل أن يبدأ. القاعدة هنا: لا يبدأ تدفقٌ يستهلك موارد قبل أن
 * يعرف صاحبه تكلفته وعدد أسئلته وهل يكفيه رصيده.
 */
final class Preflight
{
    /**
     * دقيقة لكل ثلاثة أسئلة تقديرًا محافظًا — الوعد الأقصر من الواقع
     * يُفقد الثقة أسرع مما يكسبها.
     */
    private const QUESTIONS_PER_MINUTE = 3;

    public function __construct(
        private readonly CreditManager $credits,
        private readonly FallbackChainGateway $providers,
        private readonly SpendGuard $spend,
    ) {}

    public function forTool(Tool $tool, ?Workspace $workspace, ?Project $project = null): PreflightResult
    {
        $version = $tool->currentVersion;

        if ($version === null || ! $tool->isRunnable()) {
            return PreflightResult::unavailable();
        }

        $cost = (int) $version->credit_cost;
        $balance = $workspace !== null
            ? $this->credits->walletFor($workspace)->balance
            : 0;

        $total = $version->fields()->count();
        $known = $project !== null ? $this->knownAnswers($project, $version->id) : 0;
        $remaining = max(0, $total - $known);

        // ترتيب الفحص مقصود: قدرتنا **قبل** رصيده. من لا نستطيع خدمته
        // لا يُقال له «اشحن رصيدك» — فيدفع ثمن عطلٍ عندنا (INV-8).
        $outcome = match (true) {
            ! $this->providers->hasCapacity(), ! $this->spend->hasCapacity() => PreflightOutcome::ProviderUnavailable,
            $cost > $balance => PreflightOutcome::InsufficientCredits,
            default => PreflightOutcome::Ready,
        };

        return new PreflightResult(
            outcome: $outcome,
            cost: $cost,
            balance: $balance,
            questionsTotal: $total,
            // ما نعد به هو ما سيُسأل فعلًا: عرض العدد الكامل مع أن نصفه
            // معروف يجعل الرقم عقبةً في ذهن المستخدم بلا سبب.
            questionsRemaining: $remaining,
            estimatedMinutes: max(1, (int) ceil($remaining / self::QUESTIONS_PER_MINUTE)),
        );
    }

    /**
     * البوابة على حزمة الأدوات كاملة — الاستشارة الشاملة.
     *
     * **هذا موقع العطل الأصلي.** الشاشة كانت تقول «ابدأ الاستشارة» بلا
     * كلمة عن تكلفتها، فيجيب صاحب النشاط عن ستين سؤالًا ثم يصطدم بأن
     * الحزمة تكلّف تسعة وخمسين رصيدًا وخطته تمنحه أربعين. الجدار كان
     * قائمًا قبل أن يضغط الزر، ولم يُخبره به أحد.
     *
     * والحزمة لا تُعامَل «الكل أو لا شيء»: من يكفيه سبع أدوات من إحدى
     * عشرة يبدأ بالسبع مرتّبةً بالأثر، ويعرف ما تبقّى.
     */
    public function forBundle(?Workspace $workspace, ?Project $project = null): BundlePreflightResult
    {
        $tools = Tool::runnable()->with('currentVersion')->orderBy('sort_order')->get();

        $balance = $workspace !== null
            ? $this->credits->walletFor($workspace)->balance
            : 0;

        $total = 0;
        $affordableCount = 0;
        $running = 0;

        foreach ($tools as $tool) {
            $cost = (int) ($tool->currentVersion?->credit_cost ?? 0);
            $total += $cost;

            if ($running + $cost <= $balance) {
                $running += $cost;
                $affordableCount++;
            }
        }

        // قدرتنا تسبق رصيده هنا أيضًا (INV-8).
        if (! $this->providers->hasCapacity() || ! $this->spend->hasCapacity()) {
            $outcome = PreflightOutcome::ProviderUnavailable;
        } elseif ($affordableCount === $tools->count()) {
            $outcome = PreflightOutcome::Ready;
        } elseif ($affordableCount > 0) {
            $outcome = PreflightOutcome::PartialBudget;
        } else {
            $outcome = PreflightOutcome::InsufficientCredits;
        }

        return new BundlePreflightResult(
            outcome: $outcome,
            toolsTotal: $tools->count(),
            toolsAffordable: $affordableCount,
            cost: $total,
            affordableCost: $running,
            balance: $balance,
        );
    }

    /**
     * ما سبق أن أجاب عنه المستخدم في هذا المشروع فلا يُسأل عنه ثانية.
     *
     * يُقرأ من إجابات تشغيلات المشروع السابقة على النسخة نفسها؛ ونعدّه
     * تقديرًا للعرض لا عقدًا — الحسم الفعلي عند بناء الخطوات.
     */
    private function knownAnswers(Project $project, int $toolVersionId): int
    {
        return (int) $project->runs()
            ->where('tool_version_id', $toolVersionId)
            ->join('tool_run_answers', 'tool_run_answers.tool_run_id', '=', 'tool_runs.id')
            ->distinct('tool_run_answers.field_key')
            ->count('tool_run_answers.field_key');
    }
}
