<?php

namespace App\Modules\Competitors\AdLibraries;

use App\Contracts\AdLibraryProvider;
use App\Models\CompetitorAdSnapshot;
use App\Models\Project;
use App\Models\ProjectCompetitor;
use App\Modules\Measurement\Exceptions\BudgetExhausted;
use App\Modules\Measurement\QueryBudgetManager;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * سحب مكتبات الإعلانات لمنافسي نشاط، بحدّ الأمانة (§١٠).
 *
 * لكل منافس مؤكَّد × كل منصة يدعمها المزوّد: يُحجز موضع من السقف، ثم يُسحب،
 * وتُخزَّن اللقطة بحالتها. القواعد التي تحرسها هذه الطبقة:
 *
 *   - **الحجز قبل الاستدعاء** (§٩): السحب عبر مزوّد قد يكلّف، فلا يبدأ إلا
 *     وقد ضُمن له موضع. نفاد السقف يوقف بلا اختلاق.
 *   - **الانكسار تغطية لا صفر** (§٤.٣): فشل السحب يُخزَّن `broke` بملاحظة،
 *     لا كـ«لا إعلانات له». الفرق بين عطلنا وحقيقته يبقى ظاهرًا.
 *   - **لا شبكة في المزوّد الافتراضي:** غير المضبوط يعلن الغياب بلا حجز ولا
 *     استدعاء — الحالة الأصدق اليوم.
 *
 * لا يُستدعى داخل الطلب (§٨): من أمر مجدول أو Job، كاكتشاف المنافسين.
 */
class AdLibraryScan
{
    /** موضع واحد من السقف لكل محاولة سحب منصة. */
    private const QUERIES_PER_FETCH = 1;

    public function __construct(
        private readonly AdLibraryProvider $provider,
        private readonly QueryBudgetManager $budgets,
    ) {}

    /**
     * سحب لكل منافس مؤكَّد على كل منصة مدعومة، وتخزين اللقطات.
     *
     * @return array<string, int> ملخّص: fetched/broke/unavailable وعدد المنافسين
     */
    public function forProject(Project $project): array
    {
        $competitors = $project->competitors()->confirmed()->get();
        $summary = ['competitors' => $competitors->count(), 'fetched' => 0, 'broke' => 0, 'unavailable' => 0];

        // مزوّد غير مضبوط: تُعلَن كل منصة غائبة التغطية مرة، بلا حجز ولا شبكة.
        $platforms = $this->provider->isAvailable()
            ? $this->provider->supportedPlatforms()
            : self::DECLARED_PLATFORMS;

        foreach ($competitors as $competitor) {
            foreach ($platforms as $platform) {
                $snapshot = $this->scanOne($project, $competitor, $platform);
                $this->store($competitor, $snapshot);
                $summary[$snapshot->status] = ($summary[$snapshot->status] ?? 0) + 1;
            }
        }

        return $summary;
    }

    /** المنصات التي نُعلن تغطيتها غائبةً حين لا مزوّد — لا نصمت عنها. */
    private const DECLARED_PLATFORMS = ['meta', 'google', 'tiktok'];

    private function scanOne(Project $project, ProjectCompetitor $competitor, string $platform): AdSnapshot
    {
        if (! $this->provider->isAvailable()) {
            return $this->provider->fetch($platform, $competitor->name);
        }

        $advertiser = $competitor->url ?: $competitor->name;

        try {
            $reservation = $this->budgets->reserve(
                $project->workspace,
                self::QUERIES_PER_FETCH,
                "ad_library:{$platform}",
                $project,
            );
        } catch (BudgetExhausted) {
            // نفاد السقف ليس فشل سحب: تغطية غائبة معلنة، لا `broke`.
            return AdSnapshot::unavailable($platform, 'توقّف السحب: بلغت مساحتك سقف استعلامات الشهر.');
        }

        try {
            $snapshot = $this->provider->fetch($platform, $advertiser);
            $cost = $snapshot->isFetched() ? 0.0 : 0.0;
            $this->budgets->settle($reservation, $cost, self::QUERIES_PER_FETCH);

            return $snapshot;
        } catch (Throwable $exception) {
            // الموضع يعود: عطل السحب لا يُحاسَب عليه صاحب المساحة.
            $this->budgets->release($reservation);
            Log::warning('تكسّر سحب مكتبة إعلانات.', [
                'competitor' => $competitor->id,
                'platform' => $platform,
                'reason' => $exception->getMessage(),
            ]);

            return AdSnapshot::broke($platform, 'تعذّر قراءة مكتبة الإعلانات — قد تكون الصفحة تغيّرت.');
        }
    }

    private function store(ProjectCompetitor $competitor, AdSnapshot $snapshot): void
    {
        CompetitorAdSnapshot::create([
            'project_competitor_id' => $competitor->id,
        ] + $snapshot->toRow());
    }
}
