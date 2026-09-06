<?php

declare(strict_types=1);

namespace App\Support\Navigation;

use App\Support\Experience\Experience;

/**
 * بنية الملاحة كبيانات لا كمصفوفة في قالب (INV-6).
 *
 * حين كانت القائمة مكتوبة داخل Blade، أشار ثلاثة عناصر إلى المسار نفسه
 * بلا أن يُظهر ذلك اختبارٌ أو مراجعة: «مشاريعي» و«الخطة والمهام»
 * و«التقارير» كلها `projects.index`. القائمة هنا تُقرأ برمجيًا، فيصبح
 * السؤال «هل يشير عنصران إلى وجهة واحدة؟» قابلًا للفحص آليًا.
 */
final class NavRegistry
{
    /**
     * @return array<int, NavItem>
     */
    public static function primary(Experience $experience): array
    {
        return $experience === Experience::LEARNING
            ? self::learning()
            : self::business();
    }

    /**
     * @return array<int, NavItem>
     */
    private static function business(): array
    {
        return [
            new NavItem(__('اليوم'), 'app.dashboard', activePatterns: ['app.dashboard']),
            new NavItem(__('مشاريعي'), 'app.projects.index', activePatterns: ['app.projects.*']),
            new NavItem(__('التشخيص'), 'app.tools.index', activePatterns: ['app.tools.*', 'app.runs.*']),
            new NavItem(__('الخطة والمهام'), 'app.plan', activePatterns: ['app.plan', 'app.tasks.*']),
            new NavItem(__('تقاريري'), 'app.reports.index', activePatterns: ['app.reports.*']),
        ];
    }

    /**
     * @return array<int, NavItem>
     */
    private static function learning(): array
    {
        return [
            new NavItem(__('مساري'), 'app.dashboard', activePatterns: ['app.dashboard']),
            new NavItem(__('الدروس'), 'content.index'),
            new NavItem(__('تطبيقاتي'), 'app.learning.marketing.home', activePatterns: ['app.learning.marketing.*']),
        ];
    }
}
