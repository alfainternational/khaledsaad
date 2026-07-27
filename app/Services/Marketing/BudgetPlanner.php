<?php

namespace App\Services\Marketing;

use App\Models\Project;

/**
 * يفصل «ميزانية التسويق» عن «تكلفة التعاقد».
 *
 * المشكلة التي يعالجها: المستخدم يكتب رقمًا واحدًا — 1000 مثلًا — فتُبنى
 * عليه أرقام متوقعة كأنه كله إنفاق إعلاني، بينما التعاقد مع وكالة بنطاق
 * شامل قد يستهلكه كاملًا قبل أن يصل ريال واحد إلى الإعلان. الرقم الصادق
 * هو ما يتبقى للوسائط بعد الأتعاب والإنتاج والأدوات، وهو وحده ما تُحسب
 * عليه الطاقة والعائد.
 *
 * كل الأرقام المرجعية نطاقات تخطيطية من config/agency_costs.php، تُعرض
 * موسومة بذلك ويضبطها الآدمن. لا ندّعي أنها أسعار مرصودة.
 */
class BudgetPlanner
{
    public const VERDICT_UNKNOWN = 'unknown';

    public const VERDICT_SUFFICIENT = 'sufficient';

    public const VERDICT_TIGHT = 'tight';

    public const VERDICT_INSUFFICIENT = 'insufficient';

    /**
     * @param  array<int, string>  $services  مفاتيح الخدمات المطلوبة من الوكالة
     * @return array<string, mixed>
     */
    public function plan(
        ?float $monthlyBudget,
        ?string $geography,
        array $services = [],
        ?bool $includesAgencyFee = null,
        ?string $budgetCurrency = null,
    ): array {
        $market = $this->market($geography);
        $tier = $this->tier($services);
        $fees = $market['fees'][$tier] ?? $market['fees']['single_channel'];
        $channels = $this->paidChannels($services);
        $mediaFloor = $channels > 0 ? $market['media_floor_per_channel'] * $channels : 0;

        // أقل تكلفة تشغيل شهرية معقولة لهذا النطاق: أتعاب + أدوات + وسائط.
        $floor = $fees['min'] + $market['tools_monthly']['min'] + $mediaFloor;

        $breakdown = $this->breakdown($monthlyBudget, $includesAgencyFee, $fees, $market, $mediaFloor);

        return [
            'market' => [
                'key' => $market['key'],
                'label' => $market['label'],
                'currency' => $market['currency'],
                'currency_label' => $market['currency_label'],
                'notes' => $market['notes'],
            ],
            'tier' => [
                'key' => $tier,
                'label' => config("agency_costs.tiers.{$tier}", $tier),
                'services' => $this->serviceLabels($services),
                'paid_channels' => $channels,
            ],
            'stated_budget' => $monthlyBudget,
            /*
             * عملة المبلغ كما صرّح بها صاحبه. لا نفترضها من السوق ولا نحوّل:
             * التحويل بلا سعر صرف اليوم يُنتج رقمًا يبدو دقيقًا وهو ليس كذلك.
             */
            'budget_currency' => $budgetCurrency,
            'currency_matches_market' => $budgetCurrency !== null
                ? $budgetCurrency === $market['currency']
                : null,
            'includes_agency_fee' => $includesAgencyFee,
            'breakdown' => $breakdown,
            // الرقم الذي يُبنى عليه أي توقع. صفر يعني: لا تَعِد بشيء.
            'effective_media' => $breakdown['media'],
            'reference' => [
                'agency_fee' => $fees,
                'setup_once' => $market['setup_once'],
                'production_monthly' => $market['production_monthly'],
                'tools_monthly' => $market['tools_monthly'],
                'media_floor_per_channel' => $market['media_floor_per_channel'],
                'media_floor_total' => $mediaFloor,
                'monthly_floor' => $floor,
            ],
            'verdict' => $this->verdict($monthlyBudget, $includesAgencyFee, $floor, $breakdown, $mediaFloor),
            'disclaimer' => 'نطاقات تخطيطية للمقارنة لا أسعار مرصودة. ثبّتها بعرضين أو ثلاثة قبل أن تبني عليها قرارًا.',
        ];
    }

    public function planForProject(Project $project): array
    {
        $profile = $project->profile;

        return $this->plan(
            $profile?->monthly_budget !== null ? (float) $profile->monthly_budget : null,
            $profile?->geography,
            $profile?->agency_services ?? [],
            $profile?->budget_includes_agency_fee,
            $profile?->brief('budget_currency'),
        );
    }

    /**
     * توزيع المبلغ المعلن على بنوده.
     *
     * ثلاث حالات لا حالة واحدة:
     * - شامل الأتعاب: نطرح الأتعاب والأدوات والإنتاج، والباقي وسائط (وقد يكون سالبًا).
     * - غير شامل: المبلغ كله وسائط، والأتعاب تكلفة إضافية فوقه.
     * - غير محدد: لا نفترض — نعرض السيناريوهين ونطلب التوضيح.
     *
     * @param  array{min: float|int, max: float|int}  $fees
     * @param  array<string, mixed>  $market
     * @return array<string, mixed>
     */
    private function breakdown(
        ?float $budget,
        ?bool $includesAgencyFee,
        array $fees,
        array $market,
        float|int $mediaFloor,
    ): array {
        if ($budget === null || $budget <= 0) {
            return [
                'mode' => 'unknown',
                'media' => null,
                'agency_fee' => null,
                'production' => null,
                'tools' => null,
                'total_cost_of_ownership' => null,
            ];
        }

        if ($includesAgencyFee === false) {
            // المبلغ للوسائط فقط: التكلفة الحقيقية = المبلغ + الأتعاب.
            return [
                'mode' => 'media_only',
                'media' => $budget,
                'agency_fee' => $fees['min'],
                'production' => $market['production_monthly']['min'],
                'tools' => $market['tools_monthly']['min'],
                'total_cost_of_ownership' => $budget + $fees['min']
                    + $market['production_monthly']['min'] + $market['tools_monthly']['min'],
            ];
        }

        if ($includesAgencyFee === true) {
            $fee = min($fees['min'], $budget);
            $tools = min($market['tools_monthly']['min'], max(0, $budget - $fee));
            $remaining = max(0, $budget - $fee - $tools);
            // الإنتاج يأخذ ما لا يتجاوز خُمس المتبقي حتى لا يبتلع الوسائط كلها.
            $production = min($market['production_monthly']['min'], $remaining * 0.2);

            return [
                'mode' => 'all_inclusive',
                'media' => round(max(0, $remaining - $production), 2),
                'agency_fee' => $fee,
                'production' => round($production, 2),
                'tools' => $tools,
                'total_cost_of_ownership' => $budget,
                // ما ينقص المبلغ ليغطي الأتعاب وحدها.
                'fee_shortfall' => round(max(0, $fees['min'] - $budget), 2),
            ];
        }

        return [
            'mode' => 'undeclared',
            'media' => null,
            'agency_fee' => null,
            'production' => null,
            'tools' => null,
            'total_cost_of_ownership' => null,
            // سيناريوهان يُعرضان للمستخدم ليختار أيّهما يقصد.
            'if_inclusive_media' => round(max(0, $budget - $fees['min'] - $market['tools_monthly']['min']), 2),
            'if_media_only_total' => round($budget + $fees['min'] + $market['tools_monthly']['min'], 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $breakdown
     * @return array<string, mixed>
     */
    private function verdict(
        ?float $budget,
        ?bool $includesAgencyFee,
        float|int $floor,
        array $breakdown,
        float|int $mediaFloor,
    ): array {
        if ($budget === null || $budget <= 0) {
            return [
                'level' => self::VERDICT_UNKNOWN,
                'headline' => 'لم تُحدَّد ميزانية بعد',
                'detail' => 'بلا رقم لا يمكن تقدير ما يصل إلى الإعلان فعلًا، ولا مقارنة عروض الوكالات على أساس واحد.',
            ];
        }

        if ($includesAgencyFee === null) {
            return [
                'level' => self::VERDICT_UNKNOWN,
                'headline' => 'الرقم غير مفهوم بعد: هل يشمل أتعاب الوكالة؟',
                'detail' => 'المبلغ نفسه يعني شيئين مختلفين تمامًا. حدّد أيّهما تقصد قبل أن نحسب عليه أي توقع.',
            ];
        }

        if ($includesAgencyFee === false) {
            return [
                'level' => self::VERDICT_SUFFICIENT,
                'headline' => 'ميزانيتك للوسائط، وأتعاب الوكالة فوقها',
                'detail' => 'التكلفة الشهرية الحقيقية = ميزانية الإعلان + أتعاب الإدارة + الإنتاج والأدوات. احسب التزامك على المجموع لا على رقم الإعلان وحده.',
            ];
        }

        $media = (float) ($breakdown['media'] ?? 0);
        $shortfall = (float) ($breakdown['fee_shortfall'] ?? 0);

        if ($shortfall > 0) {
            return [
                'level' => self::VERDICT_INSUFFICIENT,
                'headline' => 'المبلغ لا يغطي أتعاب الإدارة لهذا النطاق',
                'detail' => 'بهذا النطاق تُستهلك الميزانية كاملة في الأتعاب قبل أن يصل شيء إلى الإعلان — أي إنفاق بلا وصول. اختر واحدًا: قلّص النطاق، أو ابدأ بمنفّذ مستقل، أو ارفع المبلغ.',
                'gap' => $shortfall,
            ];
        }

        if ($mediaFloor > 0 && $media < $mediaFloor) {
            return [
                'level' => self::VERDICT_INSUFFICIENT,
                'headline' => 'ما يتبقى للإعلان أقل من الحد الذي يعطي بيانات يُتعلَّم منها',
                'detail' => 'الإنفاق تحت هذا الحد لا ينتج نتائج كافية للحكم على قناة أو رسالة، فيصبح المبلغ رسوم تعلّم لا استثمارًا.',
                'gap' => round($mediaFloor - $media, 2),
            ];
        }

        if ($budget < $floor) {
            return [
                'level' => self::VERDICT_TIGHT,
                'headline' => 'الميزانية تكفي بالكاد لهذا النطاق',
                'detail' => 'قابلة للتنفيذ لكن بلا هامش للتجريب. الأفضل تقليص النطاق إلى قناة واحدة تُتقن بدل توزيع المبلغ على كل شيء.',
                'gap' => round($floor - $budget, 2),
            ];
        }

        return [
            'level' => self::VERDICT_SUFFICIENT,
            'headline' => 'الميزانية متسقة مع النطاق المطلوب',
            'detail' => 'اطلب في العرض فصلًا مكتوبًا بين أتعاب الإدارة وميزانية الوسائط، وتأكد أن الوسائط تُصرف من حسابك لا من حساب الوكالة.',
        ];
    }

    /**
     * @param  array<int, string>  $services
     */
    private function tier(array $services): string
    {
        $weight = collect($services)
            ->map(fn (string $key) => (int) config("agency_costs.services.{$key}.weight", 0))
            ->sum();

        $thresholds = config('agency_costs.tier_thresholds');

        foreach ($thresholds as $tier => $minimum) {
            if ($weight >= $minimum) {
                return $tier;
            }
        }

        return 'freelancer';
    }

    /**
     * عدد القنوات المدفوعة المطلوبة — كل قناة لها حد أدنى مستقل من الإنفاق.
     *
     * @param  array<int, string>  $services
     */
    private function paidChannels(array $services): int
    {
        return collect($services)
            ->filter(fn (string $key) => (bool) config("agency_costs.services.{$key}.needs_media", false))
            ->count();
    }

    /**
     * @param  array<int, string>  $services
     * @return array<int, string>
     */
    private function serviceLabels(array $services): array
    {
        return collect($services)
            ->map(fn (string $key) => config("agency_costs.services.{$key}.label"))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * السوق من النطاق الجغرافي المكتوب. لا نخمّن: ما لا نتعرف عليه يُعامل
     * كسوق غير محدد ويُطلب تحديده، لا يُسند لمتوسط لا يخص أحدًا.
     *
     * @return array<string, mixed>
     */
    private function market(?string $geography): array
    {
        $key = 'default';
        $text = mb_strtolower(trim((string) $geography));

        $matchers = [
            'sa' => ['سعود', 'الرياض', 'جدة', 'الدمام', 'مكة', 'saudi', 'ksa', 'riyadh', 'jeddah'],
            'sd' => ['سودان', 'الخرطوم', 'بورتسودان', 'sudan', 'khartoum'],
            'gulf' => ['امارات', 'إمارات', 'دبي', 'أبوظبي', 'قطر', 'الدوحة', 'كويت', 'عمان', 'البحرين', 'uae', 'dubai', 'qatar', 'kuwait', 'bahrain', 'oman'],
        ];

        foreach ($matchers as $candidate => $needles) {
            foreach ($needles as $needle) {
                if ($text !== '' && str_contains($text, $needle)) {
                    $key = $candidate;
                    break 2;
                }
            }
        }

        return ['key' => $key] + config("agency_costs.markets.{$key}");
    }
}
