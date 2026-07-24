<?php

namespace App\Services\Tools;

use App\Models\Project;

/**
 * ذكاء تحديد هوية المشروع: نوعه، وقطاعه، وحالته، وما يملكه اليوم.
 *
 * الناتج مفاتيح محجوزة تُدمج مع الإجابات قبل تقييم visible_when، فتستطيع
 * أي أداة أن تشرط أي سؤال بسياق المشروع بنفس صيغة الشروط الحالية:
 *   'visible_when' => ['project.maturity' => 'operating']
 *   'visible_when' => ['project.stage' => ['!idea']]
 *
 * عدد الأسئلة يتبع الحاجة لا قالبًا ثابتًا: مشروع فكرة يُسأل عن التحقق من
 * السوق ولا يُسأل عن قنواته الشغالة، ومتجر يُسأل عن سلة الشراء والإرجاع،
 * وبرنامج بالاشتراك يُسأل عن التجربة المجانية والتسرّب. جودة القياس محفوظة
 * لأن الدرجة تُحسب على الأسئلة المنطبقة وحدها.
 *
 * تمييز جوهري بين مفتاحين:
 * - business_model: ما صرّح به المستخدم (أو ما حفظته أداة سابقة). يقين،
 *   فيصلح لإظهار أسئلة إلزامية.
 * - sector: استدلال من نص الوصف. ترجيح، فيصلح لأسئلة عمق اختيارية فقط —
 *   لا نُلزم مستخدمًا بسؤال بُني على تخمين قد يكون خاطئًا.
 */
class ProjectContextResolver
{
    /**
     * المفاتيح المحجوزة التي يحقنها المحلّل — لا يجوز لأداة أن تسمي حقلًا بها.
     */
    public const KEYS = [
        'project.business_model',
        'project.sector',
        'project.stage',
        'project.maturity',
        'project.has_website',
        'project.budget_band',
    ];

    /**
     * أنواع البيع المعتمدة — مطابقة لخيارات حقل business_model في الأدوات.
     */
    private const MODELS = ['b2c', 'b2b', 'services', 'marketplace', 'saas'];

    /**
     * استدلال القطاع من نص الوصف والمجال. الترتيب من الأكثر تمييزًا للأقل،
     * وأول مجموعة تتطابق كلماتها تحسم.
     *
     * @var array<string, array<int, string>>
     */
    private const SECTOR_HINTS = [
        'saas' => ['تطبيق', 'منصة', 'اشتراك شهري', 'برمجيات', 'نظام إدارة', 'سوفتوير'],
        'ecommerce' => ['متجر', 'الشحن', 'التوصيل', 'سلة', 'منتجات', 'طلبات أونلاين'],
        'local' => ['مطعم', 'مقهى', 'كافيه', 'صالون', 'عيادة', 'محل', 'فرع', 'ورشة', 'مشغل'],
        'content' => ['محتوى', 'قناة', 'دورات', 'تدريب', 'نشرة', 'مدونة'],
        'services' => ['خدمات', 'استشار', 'تصميم', 'وكالة', 'مكتب', 'مقاولات'],
    ];

    /**
     * @return array<string, string>
     */
    public function for(?Project $project): array
    {
        // بلا مشروع نرجع السياق المحايد لا الفراغ: الفراغ كان يخفي كل سؤال
        // مشروط فيحصل المستخدم على أداة منقوصة، والصواب أن يرى المجموعة
        // القياسية كاملة حين يتعذّر التمييز.
        if ($project === null) {
            return $this->neutral();
        }

        $project->loadMissing('profile');
        $profile = $project->profile;
        $stage = $project->stage ?: 'growth';

        $context = [
            'project.stage' => $stage,
            // النضج: تبسيط ثنائي يكفي أغلب الشروط — قبل البيع وبعده.
            'project.maturity' => in_array($stage, ['idea', 'launch'], true) ? 'early' : 'operating',
            'project.has_website' => trim((string) $profile?->website) === '' ? 'no' : 'yes',
            'project.budget_band' => $this->budgetBand($profile?->monthly_budget),
            'project.sector' => $this->sector($project),
        ];

        $model = $this->declaredModel($project);

        // لا نخمّن نوع البيع: غيابه يعني ألا تظهر الأسئلة الخاصة بنوع بعينه،
        // وهو أسلم من إظهار سؤال لا يخص المستخدم بناءً على ترجيح.
        if ($model !== null) {
            $context['project.business_model'] = $model;
        }

        return $context;
    }

    /**
     * السياق القياسي: مشروع شغّال بلا نوع معلن — أوسع مجموعة أسئلة عامة.
     *
     * @return array<string, string>
     */
    private function neutral(): array
    {
        return [
            'project.stage' => 'growth',
            'project.maturity' => 'operating',
            'project.has_website' => 'no',
            'project.budget_band' => 'unknown',
            'project.sector' => 'general',
        ];
    }

    /**
     * ما صرّح به المستخدم: من ملف المشروع أولًا، ثم من ذاكرة إجاباته.
     */
    private function declaredModel(Project $project): ?string
    {
        $fromProfile = $project->profile?->business_model;

        if (is_string($fromProfile) && in_array($fromProfile, self::MODELS, true)) {
            return $fromProfile;
        }

        $stored = $project->answers()->where('field_key', 'business_model')->value('value_json');
        $fromMemory = is_array($stored) ? ($stored['value'] ?? null) : $stored;

        return is_string($fromMemory) && in_array($fromMemory, self::MODELS, true) ? $fromMemory : null;
    }

    private function sector(Project $project): string
    {
        $haystack = implode(' ', array_filter([
            $project->industry,
            $project->profile?->description,
            $project->profile?->value_proposition,
        ]));

        foreach (self::SECTOR_HINTS as $sector => $hints) {
            foreach ($hints as $hint) {
                if (mb_strpos($haystack, $hint) !== false) {
                    return $sector;
                }
            }
        }

        return 'general';
    }

    /**
     * شرائح الميزانية: تحدد عمق أسئلة الإعلان المدفوع. من لا يملك ميزانية
     * لا يُسأل عن توزيعها، ومن يملك ميزانية كبيرة يُسأل عن ضبطها بتفصيل.
     */
    private function budgetBand(mixed $budget): string
    {
        if ($budget === null || $budget === '') {
            return 'unknown';
        }

        $value = (float) $budget;

        return match (true) {
            $value <= 0 => 'none',
            $value < 3000 => 'small',
            $value < 15000 => 'medium',
            default => 'large',
        };
    }
}
