<?php

namespace App\Support\Tooling;

/**
 * مُخطِّط المخرجات الموحّدة — طبقة «Reusable Output Layer» (الدستور §17/§30).
 *
 * يحوّل إجابة أداة إلى مفتاح دلالي معياري في workspace_data (tagline, offer,
 * ideal_customer...) بقيمته الفعلية، فتقرأه الأدوات الأخرى والاستوديو والتقارير
 * لاحقاً بدل الاكتفاء بالـheadline — فتتدفّق البيانات الحقيقية بين الأدوات.
 *
 * نقي وحتمي (بلا مورد خارجي) — قابل للاختبار مباشرةً.
 */
class CanonicalOutputMapper
{
    /**
     * الخريطة: كود الأداة → [المفتاح الدلالي، حقول الإدخال المرشّحة بالأولوية].
     * المفاتيح محدودة enum معروف (الدستور §28) — إضافة مفتاح تمرّ بمراجعة.
     *
     * @var array<string, array{key: string, fields: array<int, string>}>
     */
    private const MAP = [
        'tagline-builder' => ['key' => 'tagline', 'fields' => ['end_result', 'who_help', 'unique_angle']],
        'ideal-customer' => ['key' => 'ideal_customer', 'fields' => ['customer_type', 'customer_problem']],
        'offer-builder' => ['key' => 'offer', 'fields' => ['offer_name', 'offer_result']],
        'pricing-strategy' => ['key' => 'pricing', 'fields' => ['pricing_offer', 'pricing_reason']],
        'positioning' => ['key' => 'positioning', 'fields' => ['positioning_statement', 'main_difference']],
        'market-analysis' => ['key' => 'market', 'fields' => ['market_opportunity', 'market_segment']],
        'competitor-analysis' => ['key' => 'competitors', 'fields' => ['competitor_gap', 'own_advantage']],
        'marketing-plan' => ['key' => 'marketing_plan', 'fields' => ['plan_goal', 'two_week_actions']],
        'value-ladder' => ['key' => 'value_ladder', 'fields' => ['core_offer', 'entry_offer']],
        'funnel-builder' => ['key' => 'funnel', 'fields' => ['funnel_blocker', 'funnel_entry']],
        'customer-journey' => ['key' => 'customer_journey', 'fields' => ['journey_friction']],
        'content-plan' => ['key' => 'content_plan', 'fields' => ['content_goal', 'content_topics']],
    ];

    /**
     * @param  array<string, mixed>  $inputs
     * @return array{key: string, value: string}|null
     */
    public function map(string $toolCode, array $inputs): ?array
    {
        $definition = self::MAP[$toolCode] ?? null;
        if ($definition === null) {
            return null;
        }

        foreach ($definition['fields'] as $field) {
            $value = $inputs[$field] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return ['key' => $definition['key'], 'value' => trim($value)];
            }
        }

        return null;
    }

    public function keyFor(string $toolCode): ?string
    {
        return self::MAP[$toolCode]['key'] ?? null;
    }
}
