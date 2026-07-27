<?php

namespace App\Services\Reports;

use App\Models\Project;
use App\Models\ProjectAnswer;
use App\Models\Tool;
use App\Models\ToolField;
use App\Models\ToolRun;
use App\Models\ToolVersion;
use App\Services\Tools\ProjectContextResolver;
use Illuminate\Support\Collection;

/**
 * دفتر حالة المشروع: كل ما يسأل عنه فريق الوكالة في جلسة الاستكشاف،
 * مُجابًا مسبقًا ومنسوبًا إلى مصدره وتاريخه.
 *
 * الغرض ليس التفاوض بل إلغاء الحاجة لإعادة السؤال. لذلك القاعدة هنا:
 * لا نُسقط إجابة موجودة أبدًا — أي مفتاح مُجاب لا ينتمي إلى محور معروف
 * يذهب إلى محور «بيانات إضافية» بدل أن يختفي بصمت.
 *
 * ونُظهر كذلك ما لم يُجب عنه، لكن بتمييز حالتين كانتا تُخلطان:
 *
 * 1) سُئل ولم يُجب → نقص حقيقي في معرفة المشروع، يستحق الظهور.
 * 2) لم يُسأل أصلًا → إما أن الأداة لم تُشغَّل بعد، أو أن السؤال لا يخص
 *    هذا المشروع أصلًا (شرط visible_when). هذا ليس نقصًا في المستخدم،
 *    وعدّه نقصًا كان يُنتج «تغطية 6٪» لمن أنجز بالضبط ما طُلب منه:
 *    المقام كان 117 مفتاحًا إلزاميًا من إحدى عشرة أداة، بينما يشترط
 *    موجز الوكالة ثلاث أدوات فيها 39 منها — سقف بنيوي عند 33٪ لا يُتجاوز،
 *    و55 من الـ117 مشروطة برؤية لا تُعرض لأغلب المشاريع.
 *
 * فالمقام الآن: الأسئلة الإلزامية الظاهرة فعلًا لهذا المشروع في الأدوات
 * التي شغّلها. وما عداها يظهر كفرصة باسم أداتها، لا كدين على صاحبه.
 */
class AgencyStateLedger
{
    /**
     * محاور الاستكشاف بترتيب قراءتها في المستند.
     *
     * @var array<string, array{title: string, intent: string, keys: array<int, string>}>
     */
    private const THEMES = [
        'business' => [
            'title' => 'أساسيات النشاط',
            'intent' => 'ما هو النشاط، وأين يعمل، ومن ينفّذ فيه.',
            'keys' => [
                'name', 'industry', 'stage',
                'business_model', 'description', 'what_you_sell', 'one_liner', 'geography',
                'customer_location', 'service_radius', 'website', 'website_url', 'has_website',
                'local_business', 'marketplace_presence', 'google_business', 'physical_footfall',
                'stage_visibility', 'capacity', 'weekly_hours', 'founder_strength',
                'expertise_holder', 'who_executes', 'first_hire_reason',
            ],
        ],
        'offer' => [
            'title' => 'العرض والتسعير',
            'intent' => 'ما الذي يُباع بالضبط، وبأي سعر، وبأي ضمانات وأدلة.',
            'keys' => [
                'value_proposition', 'differentiator', 'your_edge', 'what_included', 'package',
                'pricing', 'pricing_model', 'price_clarity', 'price_position', 'price_sensitivity',
                'average_price', 'average_order_value', 'margin_known', 'unit_economics', 'upsell',
                'risk_reducer', 'return_policy', 'delivery_time', 'proof', 'proof_assets', 'trust',
                'scope_items', 'out_of_scope', 'scope_guard',
            ],
        ],
        'audience' => [
            'title' => 'الجمهور والعملاء',
            'intent' => 'لمن نتحدث، وما وجعه، وكيف يقرر الشراء.',
            'keys' => [
                'audience', 'audience_clarity', 'audience_definition', 'audience_segments',
                'segment_count', 'target_customer_guess', 'best_customer', 'worst_customer',
                'customer_problem', 'customer_words', 'who', 'where_they_are', 'buyer_vs_user',
                'buying_committee', 'buying_intent', 'decision_maker', 'main_objection', 'objection',
                'expected_objection', 'motivation', 'talked_to_customers', 'validation_conversations',
                'validation_source',
            ],
        ],
        'competition' => [
            'title' => 'المنافسة والتموضع',
            'intent' => 'مع من يقارن العميل، ولماذا يختار غيرنا أحيانًا.',
            'keys' => [
                'competitor_names', 'competitors_named', 'competitor_research_depth', 'landscape',
                'positioning', 'category_clarity', 'difference', 'why_they_win', 'cannot_match',
                'switching_cost', 'comparison_pages', 'defense', 'gap', 'options_count',
                'lost_deal_reason', 'tried_and_failed', 'past_experience',
            ],
        ],
        'brand' => [
            'title' => 'العلامة والرسالة',
            'intent' => 'نبرة الخطاب، ووضوحه، وثباته عبر المنصات.',
            'keys' => [
                'brand_tone', 'clarity', 'consistency', 'confusion_signal', 'first_impression_test',
                'goal_statement', 'foundation',
            ],
        ],
        'channels' => [
            'title' => 'القنوات والنشاط القائم',
            'intent' => 'ما الذي يعمل اليوم، وبأي وتيرة، وعلى أي أصول رقمية.',
            'keys' => [
                'active_channels', 'channels', 'channels_used', 'best_channel_today',
                'best_channel_name', 'first_channel_instinct', 'ad_platforms', 'distribution',
                'distribution_channels', 'planned_distribution', 'planned_first_touch',
                'outbound_willingness', 'presale_presence', 'presale_commitment', 'reach',
                'targeting', 'watching_frequency', 'publishing_capacity', 'content_cadence',
                'content_pillars', 'pillars', 'formats', 'content_pages', 'service_pages',
                'product_pages_count', 'product_content_ready', 'catalog_focus',
                'content_experience', 'content_goal', 'content_blocker', 'search_terms',
                'search_console', 'site_speed_feel', 'landing_experience', 'domain_ready',
            ],
        ],
        'funnel' => [
            'title' => 'المسار والتحويل والاحتفاظ',
            'intent' => 'كيف يتحول المهتم إلى عميل، وأين يتسرّب، وهل يعود.',
            'keys' => [
                'capture', 'lead_capture', 'conversion', 'conversion_destination',
                'planned_conversion_path', 'checkout_steps', 'friction_points', 'expected_friction',
                'leakage', 'booking_method', 'followup_system', 'response_time', 'proposal_to_close',
                'sales_cycle', 'post_purchase', 'repeat_rate', 'retention', 'retention_motion',
                'churn_signal', 'trial_conversion', 'activation_moment', 'lead_quality_definition',
                'first_revenue_plan',
            ],
        ],
        'measurement' => [
            'title' => 'القياس والبيانات وملكية الأصول',
            'intent' => 'ما الذي يُقاس فعلًا اليوم، ومن يملك الحسابات والبيانات.',
            'keys' => [
                'tracking_maturity', 'tracking_setup', 'measurement', 'baseline_numbers', 'known_cac',
                'monthly_visitors', 'monthly_leads', 'monthly_customers', 'success_number',
                'target_metric_value', 'content_measurement', 'customer_data_ownership',
                'who_owns_assets', 'protection', 'accountability', 'review_rhythm',
            ],
        ],
        'budget_goals' => [
            'title' => 'الميزانية والأهداف والمدى الزمني',
            'intent' => 'الهدف المطلوب، والمورد المتاح، والمدة المتوقعة.',
            'keys' => [
                'monthly_budget', 'budget', 'budget_range', 'budget_focus', 'budget_split',
                'primary_goal', 'objective', 'campaign_objective', 'campaign_duration_weeks',
                'timeframe_months', 'plan', 'focus', 'demand', 'readiness', 'scope',
            ],
        ],
        'constraints' => [
            'title' => 'القيود والالتزامات',
            'intent' => 'ما الذي يحدّ من التنفيذ قبل أن يُكتشف متأخرًا.',
            'keys' => ['compliance_constraints'],
        ],
    ];

    private const OTHER_THEME = [
        'title' => 'بيانات إضافية مسجّلة',
        'intent' => 'إجابات محفوظة لا تندرج تحت محور قياسي، وتُعرض كما هي حتى لا تضيع.',
    ];

    /**
     * حقول ملف المشروع التي قد تُكتب مباشرة دون المرور بأداة.
     *
     * @var array<string, string>
     */
    private const PROFILE_FALLBACK_LABELS = [
        'business_model' => 'نوع النشاط',
        'description' => 'وصف المشروع',
        'geography' => 'النطاق الجغرافي',
        'website' => 'الموقع الإلكتروني',
        'monthly_budget' => 'الميزانية الشهرية',
        'primary_goal' => 'الهدف الأساسي',
        'value_proposition' => 'عرض القيمة',
        'channels' => 'القنوات الحالية',
    ];

    /**
     * سمات المشروع نفسه تُحفظ في ذاكرة الإجابات عند تعديل الملف يدويًا،
     * فتصل إلى الدفتر بلا تسمية لأنها ليست حقول أدوات. بدون هذه الخريطة
     * كانت تُطبع بمفتاحها الإنجليزي الخام في مستند عربي يُسلَّم لوكالة.
     *
     * @var array<string, string>
     */
    private const PROJECT_ATTRIBUTE_LABELS = [
        'name' => 'اسم المشروع',
        'industry' => 'القطاع',
        'stage' => 'مرحلة المشروع',
        'slug' => 'المعرّف داخل المنصة',
    ];

    /**
     * ذاكرة الطلب الواحد لخريطة الحقول.
     *
     * @var array<string, array<string, mixed>>|null
     */
    private ?array $catalog = null;

    public function __construct(private readonly ProjectContextResolver $context) {}

    /**
     * التسمية العربية لقيمة حقل معدود، مأخوذة من تعريف الأداة نفسها.
     *
     * تُستدعى من خارج الدفتر حتى لا تُطبع قيم مثل sales أو b2c كما هي في
     * مستند عربي، ودون تكرار قوائم الخيارات في مكان ثانٍ يتخلف عن الأول.
     */
    public function optionLabel(string $fieldKey, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $field = $this->catalog()[$fieldKey] ?? null;
        $label = $this->readable($value, $field);

        return $label === '' ? null : $label;
    }

    /**
     * @return array<string, mixed>
     */
    public function build(Project $project): array
    {
        $catalog = $this->catalog();
        $answers = $this->knownAnswers($project);
        $scope = $this->scope($project, $answers);
        $mapped = [];
        $themes = [];

        foreach (self::THEMES as $key => $theme) {
            $entry = $this->theme($theme, $answers, $catalog, $scope['asked']);
            $mapped = array_merge($mapped, $entry['used_keys']);
            unset($entry['used_keys']);

            if ($entry['answered'] !== [] || $entry['unanswered'] !== []) {
                $themes[] = ['key' => $key] + $entry;
            }
        }

        $leftovers = array_diff(array_keys($answers), $mapped);

        if ($leftovers !== []) {
            $themes[] = [
                'key' => 'other',
                'title' => self::OTHER_THEME['title'],
                'intent' => self::OTHER_THEME['intent'],
                'answered' => $this->entries($leftovers, $answers, $catalog),
                'unanswered' => [],
                'coverage_percent' => 100,
            ];
        }

        $answeredCount = collect($themes)->sum(fn (array $theme) => count($theme['answered']));
        $unansweredCount = collect($themes)->sum(fn (array $theme) => count($theme['unanswered']));
        $total = $answeredCount + $unansweredCount;

        return [
            'themes' => $themes,
            'coverage' => [
                'answered' => $answeredCount,
                'unanswered' => $unansweredCount,
                'percent' => $total === 0 ? 0 : (int) round($answeredCount / $total * 100),
                /*
                 * ما الذي قيس عليه هذا الرقم — يُعرض حتى لا يُقرأ كحكم مطلق.
                 * عناوين الأدوات صيغت كأوجاع لا كأسماء («الناس لا تفهم ماذا
                 * تقدّم»)، فتُقتبس بعلامات تنصيص وإلا اختلطت بنص الجملة.
                 */
                'basis' => $scope['tools_engaged'] === []
                    ? 'ملف المشروع فقط؛ لم تُشغَّل أداة بعد.'
                    : count($scope['tools_engaged']).' أدوات شغّلها صاحب المشروع: '
                        .collect($scope['tools_engaged'])->map(fn (string $title) => "«{$title}»")->implode('، '),
            ],
            /*
             * ما لم يُسأل بعد لأن أداته لم تُشغَّل. فرصة باسمها الصريح، لا نقص:
             * الوكالة تعرف ما يمكن إضافته، وصاحب المشروع يعرف ماذا يفتح تاليًا.
             */
            'not_covered' => $scope['not_covered'],
        ];
    }

    /**
     * نطاق القياس: ما سُئل عنه فعلًا مقابل ما لم يُفتح بابه بعد.
     *
     * «سُئل» = حقل إلزامي، وظاهر لهذا المشروع بشرط visible_when، وينتمي إلى
     * أداة شغّلها صاحب المشروع فعلًا. أي شرط ناقص يُخرج الحقل من المقام.
     *
     * @param  array<string, array<string, mixed>>  $answers
     * @return array{asked: array<int, string>, tools_engaged: array<int, string>, not_covered: array<int, array<string, mixed>>}
     */
    private function scope(Project $project, array $answers): array
    {
        // سياق الرؤية: الإجابات المعروفة + مفاتيح project.* المحجوزة،
        // بنفس الصيغة التي تُقيَّم بها الشروط داخل الأدوات تمامًا.
        $context = array_merge(
            collect($answers)->map(fn (array $answer) => $answer['value'])->all(),
            $this->context->for($project),
        );

        $engagedVersionIds = $project->runs()
            ->where('status', '!=', ToolRun::STATUS_DRAFT)
            ->distinct()
            ->pluck('tool_version_id')
            ->all();

        $asked = [];
        $engaged = [];
        $notCovered = [];

        foreach ($this->versions() as $version) {
            $tool = $version->tool;

            if ($tool === null) {
                continue;
            }

            // الحقول الإلزامية الظاهرة لهذا المشروع في هذه الأداة.
            $keys = $version->fields
                ->filter(fn (ToolField $field) => $field->required && $field->isVisible($context))
                ->pluck('key')
                ->unique();

            if ($keys->isEmpty()) {
                continue;
            }

            if (in_array($version->id, $engagedVersionIds, true)) {
                $engaged[] = $tool->title;
                $asked = array_merge($asked, $keys->all());

                continue;
            }

            // أداة لم تُشغَّل: نحسب ما تضيفه من بنود لم تُعرف بعد من مصدر آخر.
            $missing = $keys->reject(fn (string $key) => array_key_exists($key, $answers))->values();

            if ($missing->isNotEmpty()) {
                $notCovered[] = [
                    'tool_key' => $tool->key,
                    'tool' => $tool->title,
                    'adds' => $missing->count(),
                ];
            }
        }

        return [
            'asked' => array_values(array_unique($asked)),
            'tools_engaged' => array_values(array_unique($engaged)),
            'not_covered' => collect($notCovered)->sortByDesc('adds')->values()->all(),
        ];
    }

    /**
     * نسخ الأدوات الحالية بحقولها. الأدوات المعطّلة لا تدخل القياس أصلًا.
     *
     * @return Collection<int, ToolVersion>
     */
    private function versions(): Collection
    {
        return ToolVersion::query()
            ->whereIn('id', Tool::whereNotNull('current_version_id')->pluck('current_version_id'))
            ->with(['tool', 'fields'])
            ->get();
    }

    /**
     * @param  array{title: string, intent: string, keys: array<int, string>}  $theme
     * @param  array<string, array<string, mixed>>  $answers
     * @param  array<string, array<string, mixed>>  $catalog
     * @param  array<int, string>  $asked  الأسئلة التي عُرضت فعلًا على هذا المشروع
     * @return array<string, mixed>
     */
    private function theme(array $theme, array $answers, array $catalog, array $asked): array
    {
        $answeredKeys = array_values(array_filter(
            $theme['keys'],
            fn (string $key) => array_key_exists($key, $answers),
        ));

        /*
         * النقص = ما عُرض على صاحب المشروع ولم يُجب عنه. سؤال لم يُعرض
         * (أداته لم تُشغَّل، أو شرط رؤيته لا ينطبق على مشروعه) ليس نقصًا فيه،
         * وإدراجه هنا كان يحوّل القسم إلى قائمة ديون وهمية.
         */
        $unansweredKeys = array_values(array_filter(
            $theme['keys'],
            fn (string $key) => ! array_key_exists($key, $answers)
                && in_array($key, $asked, true),
        ));

        $total = count($answeredKeys) + count($unansweredKeys);

        return [
            'title' => $theme['title'],
            'intent' => $theme['intent'],
            'answered' => $this->entries($answeredKeys, $answers, $catalog),
            'unanswered' => array_map(fn (string $key) => [
                'key' => $key,
                'label' => $catalog[$key]['label'] ?? $key,
            ], $unansweredKeys),
            'coverage_percent' => $total === 0 ? 0 : (int) round(count($answeredKeys) / $total * 100),
            'used_keys' => $answeredKeys,
        ];
    }

    /**
     * @param  array<int, string>  $keys
     * @param  array<string, array<string, mixed>>  $answers
     * @param  array<string, array<string, mixed>>  $catalog
     * @return array<int, array<string, mixed>>
     */
    private function entries(array $keys, array $answers, array $catalog): array
    {
        return array_values(array_map(function (string $key) use ($answers, $catalog) {
            $answer = $answers[$key];

            return [
                'key' => $key,
                'label' => $catalog[$key]['label']
                    ?? self::PROFILE_FALLBACK_LABELS[$key]
                    ?? self::PROJECT_ATTRIBUTE_LABELS[$key]
                    ?? $key,
                'value' => $this->readable($answer['value'], $catalog[$key] ?? null),
                'source' => $answer['source'],
                'answered_at' => $answer['answered_at'],
            ];
        }, $keys));
    }

    /**
     * الإجابات المعروفة عن المشروع: ذاكرة الإجابات أولًا، ثم ملف المشروع
     * لما كُتب فيه مباشرة دون أداة.
     *
     * @return array<string, array<string, mixed>>
     */
    public function knownAnswers(Project $project): array
    {
        $project->loadMissing('profile');

        $answers = ProjectAnswer::where('project_id', $project->id)
            ->get()
            ->mapWithKeys(fn (ProjectAnswer $answer) => [$answer->field_key => [
                'value' => $answer->value_json['value'] ?? $answer->value_json,
                'source' => $answer->source_tool_key,
                'answered_at' => $answer->updated_at?->toDateString(),
            ]])
            ->reject(fn (array $answer) => $this->isEmpty($answer['value']))
            ->all();

        foreach (self::PROFILE_FALLBACK_LABELS as $key => $label) {
            $value = $project->profile?->{$key};

            if (array_key_exists($key, $answers) || $this->isEmpty($value)) {
                continue;
            }

            $answers[$key] = [
                'value' => $value,
                'source' => null,
                'answered_at' => $project->profile?->updated_at?->toDateString(),
            ];
        }

        return $answers;
    }

    /**
     * خريطة الحقول من الأدوات نفسها: التسمية والخيارات مصدرها تعريف الأداة،
     * فلا تتكرر النصوص هنا ولا تتخلف عن أي تعديل في الكتالوج.
     *
     * @return array<string, array<string, mixed>>
     */
    private function catalog(): array
    {
        // خريطة الحقول ثابتة داخل الطلب الواحد، وتُستدعى عشرات المرات عند
        // ترجمة القيم؛ بناؤها في كل مرة كان يضاعف زمن توليد المستند.
        if ($this->catalog !== null) {
            return $this->catalog;
        }

        return $this->catalog = ToolField::query()
            ->orderByDesc('tool_version_id')
            ->get(['key', 'label', 'type', 'options', 'required'])
            ->groupBy('key')
            ->map(function (Collection $fields) {
                $primary = $fields->first();

                return [
                    'label' => $primary->label,
                    'type' => $primary->type,
                    'options' => $primary->options ?? [],
                    // إلزامي في أي أداة ⇒ سؤال أساسي يستحق الظهور كنقص.
                    'required' => $fields->contains(fn (ToolField $field) => $field->required),
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $field
     */
    private function readable(mixed $value, ?array $field): string
    {
        $options = collect($field['options'] ?? [])
            ->mapWithKeys(fn ($option) => [(string) ($option['value'] ?? '') => $option['label'] ?? $option['value'] ?? ''])
            ->all();

        if (is_array($value)) {
            return collect($value)
                ->map(fn ($item) => $options[(string) $item] ?? (is_scalar($item) ? (string) $item : ''))
                ->filter()
                ->implode('، ');
        }

        if (is_bool($value)) {
            return $value ? 'نعم' : 'لا';
        }

        $scalar = is_scalar($value) ? (string) $value : '';

        if (isset($options[$scalar])) {
            return $options[$scalar];
        }

        return is_numeric($scalar) && abs((float) $scalar) >= 1000
            ? number_format((float) $scalar)
            : $scalar;
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null
            || $value === ''
            || $value === []
            || (is_string($value) && trim($value) === '');
    }
}
