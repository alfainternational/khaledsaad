<?php

namespace App\Modules\Diagnosis;

/**
 * ما يقرأه كل محور من الدماغ، وبأي وزن.
 *
 * التعريف بيان لا كود: الدرجة يجب أن تكون قابلة للشرح لصاحب النشاط («لماذا
 * ٦٢؟») ولإعادة الإنتاج من لقطة. قاعدة مكتوبة في جدول تُقرأ وتُراجَع، وقاعدة
 * مبثوثة في شروط تُنسى.
 *
 * `key`    مفتاح الحقيقة في الدماغ.
 * `label`  اسمه كما يظهر في تفصيل الدرجة.
 * `weight` وزنه داخل المحور.
 * `rule`   كيف تتحول قيمته إلى نسبة: present | count | map | range.
 *
 * المحاور ١–٦ تقرأ ما أدخله المستخدم عبر الاستقبال والأدوات. المحوران ٧ و٨
 * يقرآن ما رصدته الجامعات المستقلة عنه.
 */
class AxisRegistry
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function inputsFor(Axis $axis): array
    {
        return match ($axis) {
            Axis::StrategicClarity => [
                ['key' => 'value_proposition', 'label' => __('القيمة المميزة'), 'weight' => 3, 'rule' => 'present'],
                ['key' => 'primary_goal', 'label' => __('الهدف الأساسي'), 'weight' => 2, 'rule' => 'present'],
                ['key' => 'business_model', 'label' => __('نموذج العمل'), 'weight' => 2, 'rule' => 'present'],
                ['key' => 'geography', 'label' => __('النطاق الجغرافي'), 'weight' => 1, 'rule' => 'present'],
            ],

            Axis::AudienceUnderstanding => [
                ['key' => 'target_audience', 'label' => __('الجمهور المستهدف'), 'weight' => 3, 'rule' => 'present'],
                /*
                 * القيم هي قيم أسئلة الأدوات المنشورة حرفيًّا، لا مرادفات
                 * أنيقة لها. مفتاح لا يطابق ما يُخزَّن فعلًا يعطي صفرًا صامتًا
                 * لمن أجاب — والترجمة مكانها `IntakeFactMap` قبل الكتابة.
                 */
                ['key' => 'audience_clarity', 'label' => __('وضوح الشريحة'), 'weight' => 2, 'rule' => 'map',
                    'map' => ['documented' => 1.0, 'rough' => 0.5, 'none' => 0.0]],
                ['key' => 'customer_pains', 'label' => __('أوجاع العميل'), 'weight' => 2, 'rule' => 'count', 'target' => 3],
            ],

            Axis::PositioningMessage => [
                ['key' => 'differentiation', 'label' => __('ما يميّزك'), 'weight' => 3, 'rule' => 'present'],
                ['key' => 'offer_summary', 'label' => __('ملخص العرض'), 'weight' => 2, 'rule' => 'present'],
                ['key' => 'competitors_named', 'label' => __('منافسون محدَّدون'), 'weight' => 2, 'rule' => 'count', 'target' => 3],
            ],

            Axis::ChannelStructure => [
                ['key' => 'channels', 'label' => __('القنوات المستخدمة'), 'weight' => 3, 'rule' => 'count', 'target' => 2],
                ['key' => 'monthly_budget', 'label' => __('الميزانية الشهرية'), 'weight' => 1, 'rule' => 'present'],
                ['key' => 'channel_rationale', 'label' => __('سبب اختيار القناة'), 'weight' => 2, 'rule' => 'present'],
            ],

            Axis::MeasurementData => [
                ['key' => 'analytics_connected', 'label' => __('أداة تحليلات مربوطة'), 'weight' => 3, 'rule' => 'present'],
                ['key' => 'conversion_tracking', 'label' => __('تتبّع التحويلات'), 'weight' => 3, 'rule' => 'map',
                    'map' => ['full' => 1.0, 'basic' => 0.5, 'none' => 0.0]],
                ['key' => 'reporting_rhythm', 'label' => __('إيقاع المراجعة'), 'weight' => 1, 'rule' => 'map',
                    'map' => ['biweekly' => 1.0, 'monthly' => 0.7, 'none' => 0.0]],
            ],

            Axis::ExecutionCapacity => [
                ['key' => 'team_size', 'label' => __('حجم الفريق'), 'weight' => 2, 'rule' => 'present'],
                ['key' => 'content_cadence', 'label' => __('إيقاع النشر'), 'weight' => 2, 'rule' => 'map',
                    'map' => ['daily' => 1.0, 'weekly' => 0.8, 'irregular' => 0.35, 'none' => 0.0]],
                ['key' => 'execution_owner', 'label' => __('مسؤول التنفيذ'), 'weight' => 2, 'rule' => 'present'],
            ],

            /*
             * المحور ٧: كل مفاتيحه من `SiteAudit` و`CrawlLogAnalyzer` — لا شيء
             * منها يعتمد على وصف المستخدم لموقعه، ولذلك يبلغ `measured`.
             */
            Axis::AiReadiness => [
                ['key' => 'schema_organization', 'label' => __('بيانات المنظمة المنظَّمة'), 'weight' => 3, 'rule' => 'present'],
                ['key' => 'schema_products', 'label' => __('بيانات المنتجات المنظَّمة'), 'weight' => 2, 'rule' => 'present'],
                ['key' => 'prices_machine_readable', 'label' => __('أسعار مقروءة آليًّا'), 'weight' => 2, 'rule' => 'present'],
                ['key' => 'policy_pages', 'label' => __('صفحات السياسات'), 'weight' => 2, 'rule' => 'count', 'target' => 3],
                ['key' => 'arabic_page_structure', 'label' => __('بنية الصفحات العربية'), 'weight' => 2, 'rule' => 'map',
                    'map' => ['good' => 1.0, 'partial' => 0.5, 'poor' => 0.0]],
                ['key' => 'llms_txt', 'label' => __('ملف llms.txt'), 'weight' => 1, 'rule' => 'present'],
                ['key' => 'ai_bots_allowed', 'label' => __('بوتات الذكاء غير محجوبة'), 'weight' => 3, 'rule' => 'present'],
                ['key' => 'ai_bot_visits_30d', 'label' => __('زيارات بوتات خلال ٣٠ يومًا'), 'weight' => 2, 'rule' => 'range',
                    'min' => 0, 'max' => 50],
            ],

            /*
             * المحور ٨: مؤجَّل حتى يوجد مصدر قابل للتحقق للمقام. يُعرض بتغطية
             * صفر بدل رقم تقديري (§٤.٣)، ولا يدخل المتوسط ما دام غير مفعّل.
             */
            Axis::OwnedAssets => [
                ['key' => 'owned_contacts', 'label' => __('جهات اتصال مملوكة'), 'weight' => 3, 'rule' => 'present'],
                ['key' => 'total_reachable_audience', 'label' => __('إجمالي الجمهور المتاح'), 'weight' => 2, 'rule' => 'present'],
                ['key' => 'first_party_capture', 'label' => __('وسيلة جمع مباشرة'), 'weight' => 2, 'rule' => 'present'],
            ],
        };
    }

    /**
     * مفاتيح المحور فقط — لحساب التغطية.
     *
     * @return array<int, string>
     */
    public function keysFor(Axis $axis): array
    {
        return array_column($this->inputsFor($axis), 'key');
    }
}
