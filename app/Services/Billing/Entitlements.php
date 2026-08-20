<?php

namespace App\Services\Billing;

use App\Models\Feature;
use App\Models\Plan;
use App\Models\Workspace;
use App\Modules\Shared\I18n\StoredText;
use Illuminate\Support\Collection;

/**
 * ما الذي تسمح به خطة مساحة العمل — من عناصر الميزات، لا من نصوص.
 *
 * القاعدة الحاكمة:
 * - الخطة التي لها عناصر مختارة (isGoverned) تُحكَم بها حرفيًا: ما لم يُختَر
 *   ممنوع، وما اختير بعدد يُطبَّق بعدده.
 * - الخطة التي لم يضبط لها الآدمن أي عنصر بعد تبقى مفتوحة كما كانت، فلا
 *   ينقلب تفعيل النظام إلى منع مفاجئ على عملاء قائمين.
 *
 * القيمة null في limit/quota تعني «بلا حد».
 */
class Entitlements
{
    /** ذاكرة الطلب الواحد: الاستحقاق لا يتغير أثناء نفس الطلب. */
    private array $cache = [];

    public function __construct(private readonly SubscriptionManager $subscriptions) {}

    /**
     * خريطة الاستحقاق لخطة: key => ['enabled' => bool, 'value' => ?int, 'feature' => Feature]
     *
     * @return array<string, array{enabled: bool, value: ?int, feature: Feature}>
     */
    public function forPlan(Plan $plan): array
    {
        if (isset($this->cache[$plan->id])) {
            return $this->cache[$plan->id];
        }

        $features = Feature::active()->orderBy('sort_order')->get()->keyBy('id');
        $selected = $plan->planFeatures()->get()->keyBy('feature_id');
        $governed = $selected->isNotEmpty();

        $map = [];

        foreach ($features as $feature) {
            $row = $selected->get($feature->id);

            if ($row !== null) {
                $map[$feature->key] = [
                    'enabled' => (bool) $row->enabled,
                    'value' => $row->value,
                    'note' => $row->note,
                    'feature' => $feature,
                ];

                continue;
            }

            // لا صف صريح: الخطة المحكومة تأخذ افتراضي الفهرس،
            // والخطة غير المضبوطة تبقى مفتوحة.
            $map[$feature->key] = [
                'enabled' => $governed ? $feature->default_enabled : true,
                'value' => $governed ? $feature->default_value : null,
                'note' => null,
                'feature' => $feature,
            ];
        }

        return $this->cache[$plan->id] = $map;
    }

    /**
     * @return array<string, array{enabled: bool, value: ?int, feature: Feature}>
     */
    public function for(Workspace $workspace): array
    {
        return $this->forPlan($this->subscriptions->currentPlan($workspace));
    }

    public function allows(Workspace $workspace, string $key): bool
    {
        return $this->planAllows($this->subscriptions->currentPlan($workspace), $key);
    }

    public function planAllows(Plan $plan, string $key): bool
    {
        $entry = $this->forPlan($plan)[$key] ?? null;

        // مفتاح غير موجود في الفهرس (أو ميزة معطّلة إداريًا) لا يمنع شيئًا:
        // المنع يحتاج قرارًا صريحًا، لا غيابًا.
        if ($entry === null) {
            return true;
        }

        return $entry['enabled'];
    }

    /**
     * الحد المسموح، أو null لبلا حد. يعيد 0 إن كانت الميزة نفسها مغلقة.
     */
    public function limit(Workspace $workspace, string $key): ?int
    {
        return $this->planLimit($this->subscriptions->currentPlan($workspace), $key);
    }

    public function planLimit(Plan $plan, string $key): ?int
    {
        $entry = $this->forPlan($plan)[$key] ?? null;

        if ($entry === null) {
            return null;
        }

        if (! $entry['enabled']) {
            return 0;
        }

        return $entry['value'];
    }

    /**
     * هل يتسع الحد لعنصر إضافي بمعلوم العدد الحالي؟
     */
    public function withinLimit(Workspace $workspace, string $key, int $current): bool
    {
        $limit = $this->limit($workspace, $key);

        return $limit === null || $current < $limit;
    }

    /**
     * ما يُعرض للعميل في صفحة الأسعار: العناصر المفعّلة بأعدادها.
     *
     * @return array<int, array{key: string, label: string, name: string, value: ?int, group: string, unit: ?string, enforced: bool}>
     */
    public function summaryForPlan(Plan $plan): array
    {
        return (new Collection($this->forPlan($plan)))
            ->filter(fn (array $entry) => $entry['enabled'])
            ->map(fn (array $entry, string $key) => [
                'key' => $key,
                'name' => $entry['feature']->displayName(),
                'label' => StoredText::of($entry['note']) ?: $entry['feature']->describeValue($entry['value']),
                'value' => $entry['value'],
                'group' => $entry['feature']->group,
                'unit' => $entry['feature']->unit,
                'enforced' => $entry['feature']->isEnforced(),
            ])
            ->sortBy(fn (array $row) => $this->forPlan($plan)[$row['key']]['feature']->sort_order)
            ->values()
            ->all();
    }

    /**
     * نص العرض في صفحة الفوترة، مع الرجوع للعمود القديم إن لم تُضبط الخطة.
     *
     * @return array<int, string>
     */
    public function displayFeatures(Plan $plan): array
    {
        // النصوص الحرّة القديمة تُعرض للعميل أيضًا، فتُترجَم كغيرها.
        if (! $plan->isGoverned()) {
            return array_map(StoredText::of(...), $plan->features ?? []);
        }

        return array_map(fn (array $row) => $row['label'], $this->summaryForPlan($plan));
    }

    public function flush(): void
    {
        $this->cache = [];
    }
}
