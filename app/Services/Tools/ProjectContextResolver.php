<?php

namespace App\Services\Tools;

use App\Models\Project;

/**
 * ذكاء تحديد هوية المشروع: نوعه (كيف يبيع) وحالته (أين يقف).
 *
 * الناتج مفاتيح محجوزة تُدمج مع الإجابات قبل تقييم visible_when، فتستطيع
 * أي أداة أن تشرط أي سؤال بسياق المشروع بنفس صيغة الشروط الحالية:
 *   'visible_when' => ['project.stage' => ['growth', 'scale']]
 *
 * عدد الأسئلة يتبع الحاجة لا قالبًا ثابتًا: مشروع فكرة لا يُسأل عن قنواته
 * الشغالة، ومشروع يبيع فعلًا يُسأل عنها بعمق — دون مساس بجودة القياس لأن
 * الدرجة تُحسب على الأسئلة المنطبقة فقط.
 */
class ProjectContextResolver
{
    /**
     * المفاتيح المحجوزة التي يحقنها المحلّل — لا يجوز لأداة أن تسمي حقلًا بها.
     */
    public const KEYS = ['project.business_model', 'project.stage', 'project.maturity'];

    /**
     * استدلال نوع البيع من الوصف حين لا يصرّح به المستخدم:
     * أول مجموعة تتطابق كلماتها تحسم — الترتيب من الأكثر تمييزًا للأقل.
     *
     * @var array<string, array<int, string>>
     */
    private const MODEL_HINTS = [
        'saas' => ['تطبيق', 'منصة', 'اشتراك شهري', 'برمجيات', 'نظام إدارة'],
        'ecommerce' => ['متجر', 'منتجات', 'شحن', 'توصيل الطلبات', 'سلة'],
        'local' => ['مطعم', 'مقهى', 'صالون', 'عيادة', 'محل', 'فرع'],
        'services' => ['خدمات', 'استشار', 'تصميم', 'تدريب', 'وكالة'],
    ];

    /**
     * @return array<string, string> فارغة للضيف: يحصل على الأسئلة الأساسية فقط.
     */
    public function for(?Project $project): array
    {
        if ($project === null) {
            return [];
        }

        $project->loadMissing('profile');

        $model = $project->profile?->business_model
            ?: $project->answers()->where('field_key', 'business_model')->value('value_json')
            ?: $this->inferModel((string) $project->profile?->description.' '.(string) $project->industry);

        $stage = $project->stage ?: 'growth';

        $context = array_filter([
            'project.business_model' => is_string($model) ? $model : null,
            'project.stage' => $stage,
        ]);

        // النضج: تبسيط ثنائي يكفي أغلب الشروط — قبل البيع وبعده.
        $context['project.maturity'] = in_array($stage, ['idea', 'launch'], true) ? 'early' : 'operating';

        return $context;
    }

    private function inferModel(string $haystack): ?string
    {
        foreach (self::MODEL_HINTS as $model => $hints) {
            foreach ($hints as $hint) {
                if (mb_strpos($haystack, $hint) !== false) {
                    return $model;
                }
            }
        }

        return null;
    }
}
