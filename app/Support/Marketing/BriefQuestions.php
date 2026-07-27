<?php

namespace App\Support\Marketing;

/**
 * ما تسأل عنه الوكالة قبل أن تسعّر — مصدر واحد للنموذج والمستند والـAPI.
 *
 * معيار دخول السؤال هنا: أن يكون غيابه سببًا في أن ترفض وكالة التسعير أو
 * تسعّر بالتخمين. ما عدا ذلك يبقى خارج النموذج مهما بدا مفيدًا، لأن كل
 * سؤال إضافي يقلل احتمال إكمال الموجز أصلًا.
 *
 * `why` ليس زخرفًا: عرض سبب السؤال يرفع جودة الإجابة، ويشرح للمستخدم أن
 * المنصة لا تجمع بيانات لأجل الجمع.
 */
final class BriefQuestions
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function groups(): array
    {
        return [
            [
                'key' => 'mandate',
                'title' => 'التكليف: ما المطلوب بالضبط',
                'intent' => 'الوكالة تسعّر النطاق لا الميزانية. نطاق غير محدد يعني عرضًا غير قابل للمقارنة.',
                'fields' => self::mandateFields(),
            ],
            [
                'key' => 'money',
                'title' => 'المال: ما يصل إلى الإعلان فعلًا',
                'intent' => 'الرقم الواحد لا يكفي: أتعاب الإدارة وميزانية الوسائط بندان مختلفان، وخلطهما يُنتج توقعات لا تتحقق.',
                'fields' => self::moneyFields(),
            ],
            [
                'key' => 'history',
                'title' => 'ما جُرّب قبلًا',
                'intent' => 'أول ما تسأل عنه وكالة جادة. بدونه ستدفع ثمن اكتشاف ما تعرفه أنت أصلًا.',
                'fields' => self::historyFields(),
            ],
            [
                'key' => 'assets',
                'title' => 'الأصول والوصول',
                'intent' => 'ما تملكه يحدد متى تبدأ الوكالة فعليًا: حساب إعلاني غير جاهز يعني أسبوعين ضائعين.',
                'fields' => self::assetFields(),
            ],
            [
                'key' => 'operations',
                'title' => 'من يقرر ومن ينفّذ',
                'intent' => 'أكثر ما يُعطّل الحملات ليس الميزانية بل بطء الاعتماد وغياب من يرد على العملاء.',
                'fields' => self::operationFields(),
            ],
            [
                'key' => 'terms',
                'title' => 'شكل التعاقد',
                'intent' => 'تحديد الشكل مسبقًا يمنع عروضًا غير قابلة للمقارنة، ويحمي حقك في الحسابات والبيانات.',
                'fields' => self::termFields(),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function fields(): array
    {
        return collect(self::groups())->flatMap(fn (array $group) => $group['fields'])->all();
    }

    /**
     * @return array<string, string>
     */
    public static function rules(): array
    {
        $rules = [];

        foreach (self::fields() as $field) {
            $base = match ($field['type']) {
                'multiselect' => 'array',
                'bool' => 'nullable|in:yes,no,unknown',
                'select' => 'nullable|string|max:60',
                'textarea' => 'nullable|string|max:2000',
                default => 'nullable|string|max:300',
            };

            $rules["brief.{$field['key']}"] = $base;

            if ($field['type'] === 'multiselect') {
                $rules["brief.{$field['key']}.*"] = 'string|max:60';
            }
        }

        return $rules;
    }

    /**
     * الأسئلة التي بدونها يبقى الموجز غير قابل للتسعير.
     *
     * @return array<int, string>
     */
    public static function criticalKeys(): array
    {
        return ['services', 'success_metric', 'budget_includes_agency_fee', 'previous_attempts', 'decision_maker'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function mandateFields(): array
    {
        return [
            [
                'key' => 'services',
                'label' => 'ما الخدمات التي تريد إسنادها؟',
                'type' => 'multiselect',
                'options' => collect(config('agency_costs.services', []))
                    ->map(fn (array $service) => $service['label'])->all(),
                'why' => 'كل خدمة إضافية ترفع الأتعاب الشهرية. اختيار ما تحتاجه فعلًا أرخص من «كل شيء».',
                'critical' => true,
            ],
            [
                'key' => 'success_metric',
                'label' => 'ما رقم النجاح، وما تعريفه الدقيق؟',
                'type' => 'textarea',
                'placeholder' => 'مثال: 70 تسجيلًا مكتملًا لمتجر يرفع أول منتج خلال 30 يومًا — لا مجرد فتح حساب.',
                'why' => '«70 خلال شهر» رقم بلا معنى حتى تقول 70 ماذا: تسجيل، أم مستخدم نشط، أم عملية بيع مدفوعة. الوكالة ستُقاس على تعريفك، فاكتبه أنت.',
                'critical' => true,
            ],
            [
                'key' => 'ninety_day_outcome',
                'label' => 'ما الذي تريد أن يتغيّر خلال 90 يومًا؟',
                'type' => 'textarea',
                'why' => 'يفصل بين ما تريده الآن وما تريده لاحقًا، فلا تدفع اليوم مقابل عمل يخص السنة القادمة.',
            ],
            [
                'key' => 'start_window',
                'label' => 'متى تريد البدء، وهل هناك موسم أو موعد ثابت؟',
                'type' => 'text',
                'why' => 'الموسم يغيّر الخطة والسعر: الإطلاق قبل موسم ذروة يختلف عن إطلاق في فترة راكدة.',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function moneyFields(): array
    {
        return [
            [
                'key' => 'budget_includes_agency_fee',
                'label' => 'هل المبلغ الشهري الذي حددته يشمل أتعاب الوكالة؟',
                'type' => 'bool',
                'options' => [
                    'yes' => 'نعم — المبلغ يشمل الأتعاب والإعلان معًا',
                    'no' => 'لا — المبلغ للإعلان فقط، والأتعاب فوقه',
                    'unknown' => 'لم أحسمه بعد',
                ],
                'why' => 'هذا أهم سؤال في الصفحة. المبلغ نفسه يعني شيئين مختلفين، وكل الأرقام المتوقعة تُبنى على ما يتبقى للإعلان بعد الأتعاب.',
                'critical' => true,
            ],
            [
                'key' => 'budget_currency',
                'label' => 'بأي عملة كتبت مبلغك؟',
                'type' => 'select',
                'options' => [
                    'SAR' => 'ريال سعودي',
                    'USD' => 'دولار أمريكي',
                    'SDG' => 'جنيه سوداني',
                    'AED' => 'درهم إماراتي',
                    'EGP' => 'جنيه مصري',
                    'other' => 'عملة أخرى',
                ],
                'why' => 'الرقم بلا عملة لا يعني شيئًا: ألف بالجنيه السوداني وألف بالدولار قراران مختلفان تمامًا. ولا نحوّل نيابة عنك لأننا لا نملك سعر صرف اليوم.',
            ],
            [
                'key' => 'budget_flexibility',
                'label' => 'هل المبلغ ثابت أم يزيد إن ثبتت النتيجة؟',
                'type' => 'select',
                'options' => [
                    'fixed' => 'ثابت لا يتغير',
                    'scales' => 'يزيد إذا أثبتت القناة عائدها',
                    'testing' => 'مبلغ تجريبي لمرحلة اختبار قصيرة',
                ],
                'why' => 'الوكالة تبني خطة مختلفة تمامًا لميزانية قابلة للتوسع مقابل ميزانية مغلقة.',
            ],
            [
                'key' => 'price_point',
                'label' => 'كم يدفع العميل في المرة الواحدة تقريبًا؟',
                'type' => 'text',
                'why' => 'بدونه لا يمكن الحكم إن كانت تكلفة جلب العميل مربحة أم مُهلِكة.',
            ],
            [
                'key' => 'margin',
                'label' => 'ما هامش ربحك التقريبي من كل عملية؟',
                'type' => 'text',
                'placeholder' => 'مثال: 30٪، أو 40 ريالًا صافية من كل طلب',
                'why' => 'الهامش يحدد أقصى مبلغ يمكن دفعه لجلب عميل واحد. بدونه أي هدف رقمي تخمين.',
            ],
            [
                'key' => 'repeat_purchase',
                'label' => 'هل يعود العميل للشراء؟ كم مرة في السنة؟',
                'type' => 'text',
                'why' => 'العميل المتكرر يبرر تكلفة استحواذ أعلى بكثير من العميل لمرة واحدة.',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function historyFields(): array
    {
        return [
            [
                'key' => 'previous_attempts',
                'label' => 'ماذا جرّبت قبل الآن، كم أنفقت، وما الذي حدث؟',
                'type' => 'textarea',
                'placeholder' => 'مثال: أعلنّا على سناب شهرين بـ4000 ريال، وصلت رسائل كثيرة ولم تتحول إلى طلبات.',
                'why' => 'يمنع الوكالة من إعادة تجربة فاشلة بمالك. «لم أجرب شيئًا» إجابة صحيحة ومفيدة أيضًا.',
                'critical' => true,
            ],
            [
                'key' => 'previous_agency',
                'label' => 'هل تعاملت مع وكالة أو مستقل سابقًا؟ ولماذا انتهى التعامل؟',
                'type' => 'textarea',
                'why' => 'سبب الانتهاء يكشف ما لا تريد تكراره، وهو أوضح وصف لتوقعاتك.',
            ],
            [
                'key' => 'what_works_now',
                'label' => 'من أين يأتيك العملاء اليوم؟',
                'type' => 'textarea',
                'why' => 'القناة العاملة اليوم أرخص طريق للنمو من قناة جديدة تمامًا.',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function assetFields(): array
    {
        return [
            [
                'key' => 'assets',
                'label' => 'ما الجاهز لديك الآن؟',
                'type' => 'multiselect',
                'options' => [
                    'website' => 'موقع أو متجر يعمل',
                    'ads_accounts' => 'حسابات إعلانية باسمي',
                    'analytics' => 'تحليلات مركّبة وتعمل',
                    'pixel' => 'بكسل/تتبع تحويلات مضبوط',
                    'social' => 'حسابات تواصل نشطة',
                    'brand_kit' => 'هوية بصرية وملفات جاهزة',
                    'product_media' => 'صور أو فيديوهات للمنتج',
                    'content_library' => 'محتوى مكتوب سابق',
                    'crm' => 'نظام لإدارة العملاء',
                    'email_list' => 'قائمة بريد أو أرقام عملاء',
                    'payment' => 'وسيلة دفع إلكتروني تعمل',
                ],
                'why' => 'كل بند ناقص هنا يتحول إلى بند مدفوع في عرض الوكالة، أو إلى أسبوعين تأخير قبل أول إعلان.',
                'critical' => true,
            ],
            [
                'key' => 'account_ownership',
                'label' => 'هل الحسابات باسمك وتستطيع منح صلاحية دون نقل ملكية؟',
                'type' => 'select',
                'options' => [
                    'mine' => 'نعم، كلها باسمي',
                    'partial' => 'بعضها باسم طرف آخر',
                    'none' => 'لا أملك حسابات بعد',
                ],
                'why' => 'الحسابات باسم الوكالة تعني أن تاريخك الإعلاني يبقى عندها إن انتهى التعاقد. هذا أكثر ما يندم عليه أصحاب المشاريع.',
            ],
            [
                'key' => 'payment_constraints',
                'label' => 'هل هناك قيود على الدفع للمنصات الإعلانية؟',
                'type' => 'textarea',
                'why' => 'في بعض الأسواق يتعذّر الدفع المباشر لمنصات الإعلان، فيصبح من يدفع وكيف بندًا تعاقديًا لا تفصيلًا.',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function operationFields(): array
    {
        return [
            [
                'key' => 'decision_maker',
                'label' => 'من يعتمد المحتوى والصرف؟',
                'type' => 'text',
                'why' => 'اعتماد بلا صاحب قرار واضح يوقف الحملات أسابيع. الوكالة تحتاج اسمًا واحدًا.',
                'critical' => true,
            ],
            [
                'key' => 'approval_time',
                'label' => 'كم يستغرق اعتماد عمل معروض عليك؟',
                'type' => 'select',
                'options' => [
                    'same_day' => 'في نفس اليوم',
                    'two_days' => 'يومان',
                    'week' => 'أسبوع',
                    'unclear' => 'غير منتظم',
                ],
                'why' => 'إيقاع الاعتماد يحدد كم إعلانًا يمكن اختباره شهريًا، وهو أهم من حجم الميزانية في المراحل الأولى.',
            ],
            [
                'key' => 'lead_response_owner',
                'label' => 'من يرد على العملاء المحتملين، وخلال كم من الوقت؟',
                'type' => 'text',
                'why' => 'حملة ناجحة مع رد متأخر تُنتج فاتورة بلا مبيعات. الوكالة تحتاج أن تعرف هذا قبل أن تَعِد بشيء.',
            ],
            [
                'key' => 'internal_capacity',
                'label' => 'من في فريقك سيعمل مع الوكالة؟',
                'type' => 'text',
                'why' => 'وجود شخص مسؤول من طرفك يختصر نصف الاجتماعات.',
            ],
            [
                'key' => 'constraints',
                'label' => 'ما الممنوع: ادعاءات، لهجة، منافسون، قيود قانونية أو قطاعية؟',
                'type' => 'textarea',
                'why' => 'أرخص وقت لمعرفة الممنوع هو قبل الإنتاج لا بعد رفض المحتوى.',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function termFields(): array
    {
        return [
            [
                'key' => 'engagement_model',
                'label' => 'ما شكل التعاقد الذي تفضّله؟',
                'type' => 'select',
                'options' => [
                    'retainer' => 'أتعاب شهرية ثابتة',
                    'project' => 'مشروع محدد بنطاق ومدة',
                    'performance' => 'جزء ثابت وجزء مرتبط بالنتيجة',
                    'unknown' => 'أريد اقتراح الوكالة',
                ],
                'why' => 'تحديده يجعل العروض قابلة للمقارنة على أساس واحد بدل أن تقارن أشكالًا مختلفة.',
            ],
            [
                'key' => 'contract_duration',
                'label' => 'ما المدة التي تقبل الالتزام بها ابتداءً؟',
                'type' => 'select',
                'options' => [
                    'one_month' => 'شهر تجريبي',
                    'three_months' => 'ثلاثة أشهر',
                    'six_months' => 'ستة أشهر',
                    'twelve_months' => 'سنة',
                ],
                'why' => 'أغلب القنوات لا تُحكم عليها قبل شهرين إلى ثلاثة. المدة القصيرة جدًا تُنتج حكمًا مبكرًا وخسارة مضاعفة.',
            ],
            [
                'key' => 'evaluation_criteria',
                'label' => 'كيف ستختار بين العروض؟',
                'type' => 'textarea',
                'why' => 'إعلان معيارك يجعل الوكالات تخاطبه مباشرة بدل أن ترسل عرضًا عامًا.',
            ],
        ];
    }
}
