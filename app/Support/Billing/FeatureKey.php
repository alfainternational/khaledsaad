<?php

namespace App\Support\Billing;

/**
 * مفاتيح الميزات المُطبَّقة فعلًا في الكود.
 *
 * كل ثابت هنا له نقطة منع حقيقية (middleware أو خدمة). ما ليس هنا لا يُمنع،
 * ولذلك يُنشأ في الفهرس كميزة عرضية (display) لا كبوابة — حتى لا نبيع
 * ما لا نستطيع تطبيقه.
 */
final class FeatureKey
{
    /** حد المشاريع في مساحة العمل. */
    public const PROJECTS_LIMIT = 'projects.limit';

    /** عدد تشغيلات الأدوات في الشهر الميلادي الجاري. */
    public const TOOL_RUNS_MONTHLY = 'tools.monthly_runs';

    /** تصدير التقرير PDF. */
    public const REPORTS_PDF = 'reports.pdf';

    /** تقرير الوكالة الموحّد. */
    public const REPORTS_AGENCY = 'reports.agency';

    /** المراجعة البشرية بدل خط الأنابيب الآلي. */
    public const MANUAL_REVIEW = 'reports.manual_review';

    /** نبض النمو الدوري. */
    public const GROWTH_PULSE = 'growth.pulse';

    /** حزمة الظهور للآلات GEO. */
    public const GROWTH_GEO = 'growth.geo';

    /** عدد التقارير الحيّة المتابَعة. */
    public const WATCHERS_LIMIT = 'growth.watchers';

    /** مختبر الجمهور الاصطناعي. */
    public const AUDIENCE_LAB = 'audience.lab';

    /** عدد المنافسين لكل مشروع. */
    public const COMPETITORS_LIMIT = 'competitors.limit';

    /** تتبّع مؤشرات الأداء. */
    public const KPI_TRACKING = 'kpi.tracking';

    /**
     * كل مفاتيح البوابات.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::PROJECTS_LIMIT,
            self::TOOL_RUNS_MONTHLY,
            self::REPORTS_PDF,
            self::REPORTS_AGENCY,
            self::MANUAL_REVIEW,
            self::GROWTH_PULSE,
            self::GROWTH_GEO,
            self::WATCHERS_LIMIT,
            self::AUDIENCE_LAB,
            self::COMPETITORS_LIMIT,
            self::KPI_TRACKING,
        ];
    }
}
