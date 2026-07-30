<?php

namespace App\Modules\Intake;

use App\Modules\Diagnosis\Axis;

/**
 * من أين تأتي حقائق المحاور ١–٦، وكيف تُطبَّع قبل دخول الدماغ.
 *
 * بيان لا كود، للسبب نفسه الذي جعل `AxisRegistry` بيانًا: الدرجة يجب أن
 * تُشرح لصاحب النشاط، وسلسلة مصادر مبثوثة في شروط `if` لا تُقرأ ولا تُراجَع.
 *
 * لماذا طبقة ترجمة أصلًا بدل تسمية حقول الأدوات بأسماء الدماغ؟ لأن السؤال
 * الواحد يُطرح بصيغ مختلفة في سبع أدوات — «ما الذي يميّزك» هو `differentiator`
 * هنا و`your_edge` هناك و`positioning` في ثالثة. توحيدها في الأدوات يعني
 * تعديل بيانات مستخدمين وأسئلة منشورة؛ توحيدها هنا يكلّف سطرًا.
 *
 * بنية التعريف:
 *   `axis`      المحور الذي يقرأ هذا المفتاح.
 *   `answers`   مفاتيح `project_answers` مرتَّبة بالأولوية.
 *   `profile`   حقل مقابل في `project_profiles`، أو null.
 *   `relation`  علاقة على المشروع (`audiences` | `competitors`) بدل الإجابات.
 *   `shape`     text | list | number | choice.
 *   `values`    لـ`choice` فقط: ترجمة القيمة الخام إلى القيمة المعيارية.
 *   `merge`     true = تُجمع كل المصادر؛ false = أول مصدر غير فارغ يفوز.
 *
 * كل ما يُكتب من هنا `inferred` بلا استثناء — مصدره وصف صاحب النشاط لنفسه،
 * ولا ترفعه دقة المعادلة فوقه (§٤.١ و§١٥).
 */
final class IntakeFactMap
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            // ── المحور ١: الوضوح الاستراتيجي ──────────────────────────────
            'value_proposition' => [
                'axis' => Axis::StrategicClarity,
                'answers' => ['value_proposition', 'one_liner', 'why_they_win'],
                'profile' => 'value_proposition',
                'shape' => 'text',
            ],
            'primary_goal' => [
                'axis' => Axis::StrategicClarity,
                /*
                 * `campaign_objective` خارج القائمة عمدًا: هو هدف إعلان بعينه في
                 * `campaign-planner` («ما النتيجة التي تريدها من هذا الإعلان؟»)،
                 * لا هدف النشاط. دمجه هنا كان يحوّل هدف حملة واحدة إلى الهدف
                 * الاستراتيجي للنشاط كله — تخمين معنى يخالف §٤.١، وبقية النظام
                 * يعامله منفصلًا أصلًا (PipelineSchemas، AgencyStateLedger).
                 */
                'answers' => ['primary_goal', 'goal_statement', 'objective'],
                'profile' => 'primary_goal',
                'shape' => 'text',
            ],
            'business_model' => [
                'axis' => Axis::StrategicClarity,
                'answers' => ['business_model', 'pricing_model'],
                'profile' => 'business_model',
                'shape' => 'text',
            ],
            'geography' => [
                'axis' => Axis::StrategicClarity,
                'answers' => ['geography', 'customer_location', 'service_radius'],
                'profile' => 'geography',
                'shape' => 'text',
            ],

            // ── المحور ٢: فهم الجمهور ─────────────────────────────────────
            'target_audience' => [
                'axis' => Axis::AudienceUnderstanding,
                'answers' => ['audience_definition', 'audience', 'target_customer_guess', 'best_customer', 'who'],
                'relation' => 'audiences',
                'shape' => 'text',
            ],
            /*
             * القيم هنا هي قيم الأداة الحقيقية (none/rough/documented) لا
             * مرادفاتها. أي قيمة خارجها لا تُكتب: تخمين المعنى يحوّل «لم يجب»
             * إلى درجة، والتغطية هي ما يجب أن يعكس الغياب لا الدرجة (§٤.٣).
             */
            'audience_clarity' => [
                'axis' => Axis::AudienceUnderstanding,
                'answers' => ['audience_clarity', 'clarity', 'category_clarity'],
                'shape' => 'choice',
                'values' => [
                    'documented' => 'documented',
                    'clear' => 'documented',
                    'rough' => 'rough',
                    'partial' => 'rough',
                    'none' => 'none',
                    'unclear' => 'none',
                ],
            ],
            'customer_pains' => [
                'axis' => Axis::AudienceUnderstanding,
                'answers' => ['customer_problem', 'friction_points', 'main_objection', 'objection',
                    'expected_objection', 'confusion_signal', 'churn_signal', 'lost_deal_reason'],
                'shape' => 'list',
                'merge' => true,
            ],

            // ── المحور ٣: التموضع والرسالة ────────────────────────────────
            'differentiation' => [
                'axis' => Axis::PositioningMessage,
                'answers' => ['differentiator', 'difference', 'your_edge', 'positioning', 'cannot_match'],
                'shape' => 'text',
            ],
            'offer_summary' => [
                'axis' => Axis::PositioningMessage,
                'answers' => ['what_you_sell', 'package', 'what_included', 'description', 'scope'],
                'profile' => 'description',
                'shape' => 'text',
            ],
            'competitors_named' => [
                'axis' => Axis::PositioningMessage,
                'answers' => ['competitor_names', 'competitors_named'],
                'relation' => 'competitors',
                'shape' => 'list',
                'merge' => true,
            ],

            // ── المحور ٤: البنية القنواتية ────────────────────────────────
            'channels' => [
                'axis' => Axis::ChannelStructure,
                'answers' => ['active_channels', 'channels_used', 'channels',
                    'distribution_channels', 'planned_distribution', 'distribution'],
                'profile' => 'channels',
                'shape' => 'list',
            ],
            'monthly_budget' => [
                'axis' => Axis::ChannelStructure,
                'answers' => ['monthly_budget', 'budget_range', 'budget'],
                'profile' => 'monthly_budget',
                'shape' => 'number',
            ],
            'channel_rationale' => [
                'axis' => Axis::ChannelStructure,
                'answers' => ['best_channel_today', 'best_channel_name', 'first_channel_instinct',
                    'budget_focus', 'focus', 'planned_first_touch'],
                'shape' => 'text',
            ],

            // ── المحور ٥: القياس والبيانات ────────────────────────────────
            'analytics_connected' => [
                'axis' => Axis::MeasurementData,
                'answers' => ['tracking_setup', 'search_console', 'measurement', 'content_measurement'],
                'shape' => 'text',
            ],
            'conversion_tracking' => [
                'axis' => Axis::MeasurementData,
                'answers' => ['tracking_maturity'],
                'shape' => 'choice',
                'values' => [
                    'full' => 'full',
                    'basic' => 'basic',
                    'none' => 'none',
                ],
            ],
            'reporting_rhythm' => [
                'axis' => Axis::MeasurementData,
                'answers' => ['review_rhythm', 'watching_frequency'],
                'shape' => 'choice',
                'values' => [
                    'biweekly' => 'biweekly',
                    'weekly' => 'biweekly',
                    'monthly' => 'monthly',
                    'none' => 'none',
                ],
            ],

            // ── المحور ٦: القدرة التنفيذية ────────────────────────────────
            'team_size' => [
                'axis' => Axis::ExecutionCapacity,
                'answers' => ['capacity', 'weekly_hours', 'publishing_capacity'],
                'shape' => 'text',
            ],
            'content_cadence' => [
                'axis' => Axis::ExecutionCapacity,
                'answers' => ['content_cadence'],
                'shape' => 'choice',
                'values' => [
                    'daily' => 'daily',
                    'weekly' => 'weekly',
                    'irregular' => 'irregular',
                    'none' => 'none',
                ],
            ],
            'execution_owner' => [
                'axis' => Axis::ExecutionCapacity,
                'answers' => ['who_executes', 'accountability', 'expertise_holder', 'decision_maker'],
                'shape' => 'text',
            ],
        ];
    }

    /**
     * كل مفاتيح الإجابات التي تهمّ الجامع — لتصفية الاستعلام مرة واحدة.
     *
     * @return array<int, string>
     */
    public static function answerKeys(): array
    {
        $keys = [];

        foreach (self::all() as $definition) {
            $keys = [...$keys, ...($definition['answers'] ?? [])];
        }

        return array_values(array_unique($keys));
    }
}
