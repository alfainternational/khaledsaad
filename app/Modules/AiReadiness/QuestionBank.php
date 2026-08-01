<?php

namespace App\Modules\AiReadiness;

use App\Models\Project;
use App\Modules\Shared\Sectors\Sector;

/**
 * بنك الأسئلة القطاعي — أحد أصول الخندق الأربعة (CLAUDE.md §٣).
 *
 * القاعدة الحاكمة: **لا ترجمة من الإنجليزية.** «أفضل شركة تسويق» ترجمةٌ
 * لسؤال إنجليزي ولا يكتبها مشترٍ خليجي. ما يُكتب فعلًا: «مين أفضل شركة تسويق
 * في الرياض؟» و«كم تكلف إدارة سوشيال ميديا في السعودية؟» — بلهجة بيضاء
 * بلمسة خليجية، وبصيغة السؤال المكتوب في مربّع البحث لا في تقرير.
 *
 * لماذا يهم؟ لأن النموذج يجيب على ما يُسأل. سؤال بصيغة أكاديمية يُنتج جوابًا
 * أكاديميًّا لا يذكر أسماء متاجر، فيخرج القياس بصفر ذكر لعلامة قد تكون
 * ظاهرة تمامًا في السؤال الحقيقي — وهو خطأ قياس يُقرأ حكمًا على النشاط.
 *
 * الأسئلة تنمو بكل عميل في القطاع، وهذا ما يجعل البنك أصلًا لا ملفّ إعداد.
 */
class QuestionBank
{
    /**
     * أسئلة مشترٍ حقيقي لكل نية شراء.
     *
     * `{category}` تُستبدل بمجال النشاط، و`{city}` بمدينته. الاستبدال في
     * النصّ لا في المنطق: السؤال يُخزَّن كما سُئل، فيمكن مراجعته لاحقًا بلغة
     * من سأله.
     *
     * @var array<string, array<string, string>>
     */
    private const TEMPLATES = [
        'best_provider' => [
            'intent' => 'يبحث عن الأفضل',
            'text' => 'مين أفضل {category} في {city}؟',
        ],
        'recommendation' => [
            'intent' => 'يطلب ترشيحًا',
            'text' => 'أبغى {category} كويس في {city}، وش تنصحني؟',
        ],
        'pricing' => [
            'intent' => 'يقارن السعر',
            'text' => 'كم تكلف {category} في {city}؟',
        ],
        'shortlist' => [
            'intent' => 'يبني قائمة مختصرة',
            'text' => 'وش أشهر {category} في {city}؟',
        ],
        'trust' => [
            'intent' => 'يتحقق قبل الشراء',
            'text' => 'كيف أعرف {category} موثوق في {city}؟',
        ],
        'comparison' => [
            'intent' => 'يوازن بين خيارين',
            'text' => 'إيش الفرق بين {category} المحلي والعالمي في {city}؟',
        ],
    ];

    /**
     * قوالب القطاعات المتخصصة — نية المشتري تختلف باختلاف ما يشتريه.
     *
     * وليّ الأمر لا يسأل «مين أفضل تعليم» بل «أي مدرسة أسجّل ولدي»، وباحث
     * السكن يسأل بالحي لا بالمدينة. القالب العام يبقى للأساس، وهذه تحل محله
     * حين يكون القطاع معلنًا — بنفس المفاتيح حتى يصح تتبع النية زمنيًّا.
     *
     * @var array<string, array<string, array<string, string>>>
     */
    private const SECTOR_TEMPLATES = [
        Sector::EDUCATION => [
            'best_provider' => [
                'intent' => 'يبحث عن الأفضل',
                'text' => 'وش أفضل {category} في {city}؟',
            ],
            'recommendation' => [
                'intent' => 'يطلب ترشيحًا',
                'text' => 'أبغى أسجّل ولدي في {category} كويسة في {city}، وش تنصحوني؟',
            ],
            'pricing' => [
                'intent' => 'يقارن الرسوم',
                'text' => 'كم رسوم {category} في {city}؟',
            ],
            'shortlist' => [
                'intent' => 'يبني قائمة مختصرة',
                'text' => 'وش أشهر {category} عند أهل {city}؟',
            ],
            'trust' => [
                'intent' => 'يتحقق قبل التسجيل',
                'text' => 'كيف أتأكد إن {category} معتمدة ومرخّصة في {city}؟',
            ],
            'comparison' => [
                'intent' => 'يوازن بين خيارين',
                'text' => 'أيهما أفضل لولدي: {category} أهلية ولا حكومية في {city}؟',
            ],
        ],
        Sector::ECOMMERCE => [
            'best_provider' => [
                'intent' => 'يبحث عن الأفضل',
                'text' => 'وش أفضل متجر {category} يوصّل داخل {city}؟',
            ],
            'recommendation' => [
                'intent' => 'يطلب ترشيحًا',
                'text' => 'أبغى أطلب {category} أونلاين، وش المتجر اللي تنصحوني فيه؟',
            ],
            'pricing' => [
                'intent' => 'يقارن السعر',
                'text' => 'كم سعر {category} مع التوصيل في {city}؟',
            ],
            'shortlist' => [
                'intent' => 'يبني قائمة مختصرة',
                'text' => 'وش أشهر متاجر {category} في السعودية؟',
            ],
            'trust' => [
                'intent' => 'يتحقق قبل الشراء',
                'text' => 'كيف أعرف إن متجر {category} موثوق قبل ما أدفع؟',
            ],
            'comparison' => [
                'intent' => 'يوازن بين خيارين',
                'text' => 'أطلب {category} من متجر محلي ولا من المنصات الكبيرة؟',
            ],
        ],
        Sector::REAL_ESTATE => [
            'best_provider' => [
                'intent' => 'يبحث عن الأفضل',
                'text' => 'مين أفضل {category} في {city}؟',
            ],
            'recommendation' => [
                'intent' => 'يطلب ترشيحًا',
                'text' => 'أدور شقة أو فيلا في {city}، مين {category} اللي تنصحوني أتعامل معه؟',
            ],
            'pricing' => [
                'intent' => 'يقارن الأسعار والعمولة',
                'text' => 'كم أسعار العقار وعمولة {category} في {city}؟',
            ],
            'shortlist' => [
                'intent' => 'يبني قائمة مختصرة',
                'text' => 'وش أشهر مكاتب العقار في {city}؟',
            ],
            'trust' => [
                'intent' => 'يتحقق قبل التعامل',
                'text' => 'كيف أتأكد إن {category} مرخّص وعنده فال في {city}؟',
            ],
            'comparison' => [
                'intent' => 'يوازن بين خيارين',
                'text' => 'أشتري عن طريق {category} ولا مباشرة من المطوّر في {city}؟',
            ],
        ],
    ];

    /** الحدّ الأدنى لعدد الأسئلة في دورة قابلة للنشر. */
    public const MIN_QUESTIONS = 3;

    /**
     * أسئلة هذا النشاط.
     *
     * @return array<int, array<string, string>>
     */
    public function for(Project $project, int $limit = 5): array
    {
        $category = $this->categoryOf($project);
        $city = $this->cityOf($project);

        $questions = [];

        // القطاع المعلن يبدّل مجموعة القوالب كلها لا سؤالًا بعينه.
        $templates = self::SECTOR_TEMPLATES[Sector::declaredOrGeneral($project->sector)] ?? self::TEMPLATES;

        foreach ($templates as $key => $template) {
            $questions[] = [
                'key' => $key,
                'intent' => $template['intent'],
                'text' => str_replace(
                    ['{category}', '{city}'],
                    [$category, $city],
                    $template['text'],
                ),
            ];

            if (count($questions) >= $limit) {
                break;
            }
        }

        return $questions;
    }

    /**
     * مجال النشاط بلسان مشترٍ لا بمصطلح داخلي.
     *
     * `industry` قد يكون «تجارة إلكترونية»، ولا أحد يسأل «مين أفضل تجارة
     * إلكترونية». الوصف أقرب لكلام الناس، ولذلك يسبق.
     */
    private function categoryOf(Project $project): string
    {
        $candidates = [
            $project->profile?->brief('category_label'),
            $project->industry,
            $project->name,
        ];

        foreach ($candidates as $candidate) {
            if (filled($candidate)) {
                return trim((string) $candidate);
            }
        }

        return 'مزوّد خدمة';
    }

    private function cityOf(Project $project): string
    {
        $geography = $project->profile?->geography;

        // «الرياض، السعودية» → «الرياض». السؤال الحقيقي يذكر مدينة واحدة.
        if (filled($geography)) {
            return trim(explode('،', explode(',', (string) $geography)[0])[0]);
        }

        return 'السعودية';
    }
}
