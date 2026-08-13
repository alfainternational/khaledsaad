<?php

namespace App\Modules\Reporting\Templates;

/**
 * أجساد قوالب التوصيات: الأصل الحقيقي الذي يخرج به صاحب النشاط.
 *
 * **سبب وجودها:** كانت القوالب الثلاثة عشر تُولَّد بـ`array_map` واحد، فتخرج
 * بأجساد متطابقة حرفيًّا وعناوين مختلفة. من يفتح «مصفوفة اختيار القناة» ومن
 * يفتح «بطاقة العميل الواحد» كان يرى الاستمارة نفسها: خمس كتل، ثلاث منها نصّ
 * بين أقواس يطلب منه أن يكتب. الاسم كان موجودًا والأصل غير موجود — وهو أسوأ
 * من غياب معلن، لأن المستخدم يظنّ أنه استلم أداة.
 *
 * **لماذا هنا لا في `database/data`:** استخراج الترجمة يمسح `app/` و`config/`
 * وحدهما، ولا يلتقط إلا ما مرّ بـ`__()` صراحةً. النصّ الذي يبقى في مجلد
 * البيانات لا يدخل الكتالوج، فيظهر عربيًّا في تقرير فرنسي بلا خطأ واحد في
 * السجل. وضعُه هنا هو ما يجعل الترجمة تشمله كغيره من نصوص المنتج.
 *
 * **المفتاح هو النصّ العربي:** البذر يقرأ هذه المكتبة تحت لغة المصدر فيخزّن
 * العربية نفسها، و`TemplateResolver` يعيد تمريرها على `__()` وقت العرض. فلا
 * صفّ لكل لغة في قاعدة البيانات، ولا مصدر حقيقة ثانٍ، والترجمة الناقصة تُعرض
 * عربيةً بدل أن تُخفي الكتلة.
 *
 * **النواب `{...}` تُربط ولا تُملأ باليد:** ما يعرفه النظام عن النشاط يُحقن،
 * وما لا يعرفه يصير فجوة معلنة تحمل مفتاح حقلها — فيقدر صاحبه أن يكملها من
 * الشاشة نفسها بدل أن يُقال له «ناقص» بلا باب.
 */
final class TemplateLibrary
{
    /**
     * كل القوالب بترتيب أهدافها في `database/data/reporting/objectives.php`.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            self::prioritizeGrowthFoundation(),
            self::clarifyPositioning(),
            self::defineAudience(),
            self::competitorAnalysis(),
            self::clarifyOffer(),
            self::repairConversionFunnel(),
            self::selectGrowthChannels(),
            self::improveSearchDiscovery(),
            self::buildContentEngine(),
            self::validatePaidCampaign(),
            self::prepareAgencyBrief(),
            self::establishMeasurementBaseline(),
            self::improveAiReadiness(),
        ];
    }

    /**
     * قالب هدف واحد، أو `null` إن لم يكن له قالب.
     *
     * @return array<string, mixed>|null
     */
    public static function find(string $objective): ?array
    {
        foreach (self::all() as $template) {
            if ($template['objective'] === $objective) {
                return $template;
            }
        }

        return null;
    }

    /**
     * الأصل المشترك: كل قالب يعلن هدفه ونوعه وجسده وربطه.
     *
     * `required_context` يبقى على `business_name` وحده في كل القوالب عمدًا:
     * اسم النشاط متاح دائمًا، فلا يسقط قالب بسببه. وكل ربط آخر اختياريّ،
     * وغيابه يصير فجوة معلنة تُملأ لا سببًا لحجب الورقة كلها.
     *
     * @param  array<int, array{label: string, value: string}>  $blocks
     * @param  array<int, string>  $tips
     * @param  array<int, array{field_key: string, answer_key: string, transform?: string}>  $bindings
     * @return array<string, mixed>
     */
    private static function template(
        string $objective,
        string $kind,
        string $title,
        array $blocks,
        array $tips,
        array $bindings,
    ): array {
        return [
            'objective' => $objective,
            'kind' => $kind,
            'title' => $title,
            /*
             * اسم النشاط أول كل ورقة: هذه أوراق تُطبع وتُسلَّم لموظف أو مستقلّ
             * أو وكالة، وورقةٌ بلا اسم صاحبها تضيع بين أوراق ثلاثة عملاء.
             */
            'body' => [
                'blocks' => [
                    ['label' => __('النشاط'), 'value' => '{business_name}'],
                    ...$blocks,
                ],
                'tips' => $tips,
            ],
            'required_context' => ['business_name'],
            'bindings' => array_map(
                fn (array $binding): array => [
                    'field_key' => $binding['field_key'],
                    'answer_key' => $binding['answer_key'],
                    'transform' => $binding['transform'] ?? 'text',
                ],
                [
                    ['field_key' => 'business_name', 'answer_key' => 'project.name'],
                    ...$bindings,
                ],
            ),
            'is_hypothesis' => true,
            'locale' => (string) config('locales.source', 'ar'),
            'version' => 2,
        ];
    }

    /** @return array<string, mixed> */
    private static function prioritizeGrowthFoundation(): array
    {
        return self::template(
            'prioritize-growth-foundation',
            'checklist',
            __('ورقة نقطة البداية لهذا الأسبوع'),
            [
                ['label' => __('الهدف الذي أعلنته'), 'value' => '{primary_goal}'],
                ['label' => __('أضعف ثلاثة عناصر في نتيجتك'), 'value' => __('انقل الثلاثة الأولى من قائمة الإصلاح كما هي، بلا إعادة صياغة.')],
                ['label' => __('العنصر الواحد الذي تبدأ به'), 'value' => __('اختر واحدًا من الثلاثة. الاثنان الباقيان يبقيان مكتوبين ولا يُنفَّذان هذا الأسبوع.')],
                ['label' => __('لماذا هذا أولًا'), 'value' => __('سطر واحد: ما الذي ينفتح لك حين يُصلَح هذا، ولا ينفتح بغيره.')],
                ['label' => __('الدليل بعد سبعة أيام'), 'value' => __('شيء واحد يمكن فحصه بنعم أو لا، لا شعور بالتحسن.')],
            ],
            [
                __('العناصر الثلاثة مرتبة بأثرها في درجتك، لا بسهولتها. ابدأ بالأعلى أثرًا حتى لو كان أثقل.'),
                __('لا تفتح بندًا ثانيًا قبل أن يُغلق الأول بدليل مكتوب.'),
            ],
            [
                ['field_key' => 'primary_goal', 'answer_key' => 'answers.primary_goal'],
            ],
        );
    }

    /** @return array<string, mixed> */
    private static function clarifyPositioning(): array
    {
        return self::template(
            'clarify-positioning',
            'page_outline',
            __('ورقة جملة التموضع واختبارها'),
            [
                ['label' => __('ما تبيعه اليوم'), 'value' => '{what_you_sell}'],
                ['label' => __('الفرق الذي تعلنه'), 'value' => '{differentiator}'],
                ['label' => __('الدليل على هذا الفرق'), 'value' => '{proof}'],
                ['label' => __('الجملة'), 'value' => __('نساعد [من] على [الناتج الذي يريده] عبر [الطريقة التي تميّزك]. اكتبها في سطر واحد لا سطرين.')],
                ['label' => __('اختبار الخمس ثوانٍ'), 'value' => __('اعرض الجملة على ثلاثة أشخاص خارج مجالك خمس ثوانٍ، ثم اسأل: ماذا نبيع ولمن؟ سجّل إجاباتهم حرفيًّا.')],
                ['label' => __('أين تُنشر بعد الاختبار'), 'value' => __('الصفحة الرئيسية، ووصف الحساب، وأول سطر في أي عرض ترسله. الجملة الواحدة في ثلاثة أماكن.')],
            ],
            [
                __('الجملة التي تحتاج شرحًا بعدها ليست جملة تموضع؛ أعد كتابتها حتى تقف وحدها.'),
                __('إن اختلفت إجابات الثلاثة عن بعضها فالمشكلة في الجملة لا فيهم.'),
            ],
            [
                ['field_key' => 'what_you_sell', 'answer_key' => 'answers.what_you_sell'],
                ['field_key' => 'differentiator', 'answer_key' => 'answers.differentiator'],
                ['field_key' => 'proof', 'answer_key' => 'answers.proof'],
            ],
        );
    }

    /** @return array<string, mixed> */
    private static function defineAudience(): array
    {
        return self::template(
            'define-audience',
            'checklist',
            __('بطاقة العميل الواحد'),
            [
                ['label' => __('أفضل عميل تعامَلت معه'), 'value' => '{best_customer}'],
                ['label' => __('المشكلة التي جاء بها'), 'value' => '{customer_problem}'],
                ['label' => __('أين تجده اليوم'), 'value' => '{where_they_are}'],
                ['label' => __('أول اعتراض يقوله قبل الشراء'), 'value' => '{objection}'],
                ['label' => __('من يوقّع على القرار'), 'value' => '{decision_maker}'],
                ['label' => __('الجملة التي أقنعته فعلًا'), 'value' => __('اكتبها بكلماته هو كما سمعتها، لا بكلماتك أنت. إن لم تسمعها بعد فهذه أول مكالمة تجريها.')],
            ],
            [
                __('بطاقة واحدة لعميل واحد حقيقي. شريحتان في بطاقة واحدة تعنيان أنك لن تخاطب أيًّا منهما.'),
                __('«الجميع» ليس جمهورًا. إن كان هذا جوابك فابدأ من آخر ثلاثة اشتروا منك فعلًا.'),
            ],
            [
                ['field_key' => 'best_customer', 'answer_key' => 'answers.best_customer'],
                ['field_key' => 'customer_problem', 'answer_key' => 'answers.customer_problem'],
                ['field_key' => 'where_they_are', 'answer_key' => 'answers.where_they_are'],
                ['field_key' => 'objection', 'answer_key' => 'answers.objection'],
                ['field_key' => 'decision_maker', 'answer_key' => 'answers.decision_maker'],
            ],
        );
    }

    /** @return array<string, mixed> */
    private static function competitorAnalysis(): array
    {
        return self::template(
            'competitor-analysis',
            'spreadsheet',
            __('ورقة تشريح منافس'),
            [
                ['label' => __('المنافس'), 'value' => '{competitor_names}'],
                ['label' => __('لماذا يذهب العميل إليه'), 'value' => '{why_they_win}'],
                ['label' => __('موقعك السعري مقابله'), 'value' => '{price_position}'],
                ['label' => __('ما لا تستطيع مجاراته'), 'value' => '{cannot_match}'],
                ['label' => __('ما تملكه ولا يملكه'), 'value' => '{your_edge}'],
                ['label' => __('الفراغ الذي تدخل منه'), 'value' => __('سطر واحد: ما الذي يحتاجه عميلهم ولا يقدّمه أحد منكما؟ هذا موضعك لا مواجهتهم في أقوى نقطة عندهم.')],
            ],
            [
                __('منافس واحد لكل ورقة. مقارنة ثلاثة معًا تنتهي بجدول لا يقود إلى قرار.'),
                __('«ما لا تستطيع مجاراته» ليس اعترافًا بالهزيمة — هو ما يمنعك من حرق ميزانيتك في معركة محسومة.'),
            ],
            [
                ['field_key' => 'competitor_names', 'answer_key' => 'answers.competitor_names', 'transform' => 'csv'],
                ['field_key' => 'why_they_win', 'answer_key' => 'answers.why_they_win'],
                ['field_key' => 'price_position', 'answer_key' => 'answers.price_position'],
                ['field_key' => 'cannot_match', 'answer_key' => 'answers.cannot_match'],
                ['field_key' => 'your_edge', 'answer_key' => 'answers.your_edge'],
            ],
        );
    }

    /** @return array<string, mixed> */
    private static function clarifyOffer(): array
    {
        return self::template(
            'clarify-offer',
            'page_outline',
            __('ورقة العرض في دقيقة'),
            [
                ['label' => __('ما يشمله العرض'), 'value' => '{what_included}'],
                ['label' => __('السعر المعلن'), 'value' => '{average_price}'],
                ['label' => __('مدة التسليم'), 'value' => '{delivery_time}'],
                ['label' => __('أول اعتراض وردّه'), 'value' => '{main_objection}'],
                ['label' => __('ما يقلّل مخاطرة العميل'), 'value' => '{risk_reducer}'],
                ['label' => __('الخطوة التالية بعد الاقتناع'), 'value' => __('فعل واحد محدد: احجز، أو اطلب، أو راسل على هذا الرقم. «تواصل معنا» ليست خطوة.')],
            ],
            [
                __('العميل الذي يسأل عن السعر ثم يختفي لم يفهم ما يشمله العرض، لا أنه وجده غاليًا.'),
                __('اقرأ الورقة بصوت عالٍ في دقيقة. ما لا يُقال في دقيقة لا يُشترى في مكالمة.'),
            ],
            [
                ['field_key' => 'what_included', 'answer_key' => 'answers.what_included'],
                ['field_key' => 'average_price', 'answer_key' => 'answers.average_price'],
                ['field_key' => 'delivery_time', 'answer_key' => 'answers.delivery_time'],
                ['field_key' => 'main_objection', 'answer_key' => 'answers.main_objection'],
                ['field_key' => 'risk_reducer', 'answer_key' => 'answers.risk_reducer'],
            ],
        );
    }

    /** @return array<string, mixed> */
    private static function repairConversionFunnel(): array
    {
        return self::template(
            'repair-conversion-funnel',
            'checklist',
            __('ورقة اختبار نقطة التسرب'),
            [
                ['label' => __('زوّار الشهر'), 'value' => '{monthly_visitors}'],
                ['label' => __('من تواصل منهم'), 'value' => '{monthly_leads}'],
                ['label' => __('من اشترى منهم'), 'value' => '{monthly_customers}'],
                ['label' => __('أين يسقط أكبر عدد'), 'value' => __('احسب النسبة بين كل رقمين متجاورين. أكبر هبوط بينهما هو نقطة التسرب، لا ما تشعر أنه المشكلة.')],
                ['label' => __('فرضيتك لسبب السقوط'), 'value' => '{friction_points}'],
                ['label' => __('التغيير الواحد الذي تجرّبه'), 'value' => __('تغيير واحد فقط لأسبوعين. تغييران معًا يجعلان النتيجة غير قابلة للنسبة إلى أيهما.')],
                ['label' => __('الرقم الذي يثبت النجاح'), 'value' => __('نفس النسبة التي قِستها أعلاه، مقارنة بأسبوعين قبل التغيير.')],
            ],
            [
                __('إن كان أحد الأرقام الثلاثة مجهولًا فهذه أول مهمة: لا يُصلَح مسار لا يُقاس.'),
                __('نقطة التسرب الحقيقية غالبًا قبل ما تتوقعه بخطوة.'),
            ],
            [
                ['field_key' => 'monthly_visitors', 'answer_key' => 'answers.monthly_visitors'],
                ['field_key' => 'monthly_leads', 'answer_key' => 'answers.monthly_leads'],
                ['field_key' => 'monthly_customers', 'answer_key' => 'answers.monthly_customers'],
                ['field_key' => 'friction_points', 'answer_key' => 'answers.friction_points'],
            ],
        );
    }

    /** @return array<string, mixed> */
    private static function selectGrowthChannels(): array
    {
        return self::template(
            'select-growth-channels',
            'spreadsheet',
            __('مصفوفة اختيار القناة'),
            [
                ['label' => __('أين يوجد عميلك'), 'value' => '{customer_location}'],
                ['label' => __('أفضل قناة عندك اليوم'), 'value' => '{best_channel_today}'],
                ['label' => __('ساعاتك الأسبوعية المتاحة'), 'value' => '{weekly_hours}'],
                ['label' => __('من ينفّذ فعليًّا'), 'value' => '{who_executes}'],
                ['label' => __('قنوات جرّبتها وفشلت'), 'value' => '{tried_and_failed}'],
                ['label' => __('القناة المختارة لتسعين يومًا'), 'value' => __('قناة واحدة، أو اثنتان بحدّ أقصى. اكتب أمام كل واحدة: لماذا هي، وكم ساعة أسبوعيًّا تأخذ.')],
                ['label' => __('متى تتخلى عنها'), 'value' => __('اكتب الرقم والتاريخ الآن وأنت هادئ. القرار الذي يُؤجَّل إلى وقت الإحباط يُتخذ بالمزاج.')],
            ],
            [
                __('القناة التي فشلت سابقًا لا تُستبعد تلقائيًّا — اسأل أولًا: فشلت لأنها لا تناسب عميلك، أم لأنك لم تعطها وقتًا كافيًا؟'),
                __('الحضور في أربع قنوات بجهد ربع لا يساوي قناة واحدة بجهد كامل.'),
            ],
            [
                ['field_key' => 'customer_location', 'answer_key' => 'answers.customer_location'],
                ['field_key' => 'best_channel_today', 'answer_key' => 'answers.best_channel_today'],
                ['field_key' => 'weekly_hours', 'answer_key' => 'answers.weekly_hours'],
                ['field_key' => 'who_executes', 'answer_key' => 'answers.who_executes'],
                ['field_key' => 'tried_and_failed', 'answer_key' => 'answers.tried_and_failed', 'transform' => 'csv'],
            ],
        );
    }

    /** @return array<string, mixed> */
    private static function improveSearchDiscovery(): array
    {
        return self::template(
            'improve-search-discovery',
            'page_outline',
            __('مخطط صفحة لسؤال بحث'),
            [
                ['label' => __('السؤال بصيغته كما يُكتب في البحث'), 'value' => '{search_terms}'],
                ['label' => __('ماذا يريد الباحث فعلًا'), 'value' => __('يقارن؟ يتعلّم؟ جاهز للشراء؟ نية واحدة لكل صفحة — الصفحة التي تخدم نيتين لا تُرضي أيًّا منهما.')],
                ['label' => __('عنوان الصفحة'), 'value' => __('يحمل السؤال بصيغته، لا اسم خدمتك.')],
                ['label' => __('العناوين الفرعية'), 'value' => __('كل عنوان فرعي سؤال فرعي يسأله نفس الباحث، مرتبة كما يفكّر لا كما تبيع.')],
                ['label' => __('الدليل داخل الصفحة'), 'value' => __('رقم، أو حالة، أو صورة قبل وبعد. الصفحة بلا دليل تُقرأ ولا تُصدَّق.')],
                ['label' => __('الفعل في نهايتها'), 'value' => __('فعل واحد يناسب نية الباحث: من يقارن يحجز مكالمة، ومن جاهز يشتري مباشرة.')],
                ['label' => __('عنوان الصفحة على موقعك'), 'value' => '{website_url}'],
            ],
            [
                __('صفحة واحدة لسؤال واحد. تجميع خمسة أسئلة في صفحة يجعلها غير مطابقة لأي منها.'),
                __('اكتب السؤال كما ينطقه عميلك، لا كما تُكتب المصطلحات في مجالك.'),
            ],
            [
                ['field_key' => 'search_terms', 'answer_key' => 'answers.search_terms', 'transform' => 'csv'],
                ['field_key' => 'website_url', 'answer_key' => 'answers.website_url'],
            ],
        );
    }

    /** @return array<string, mixed> */
    private static function buildContentEngine(): array
    {
        return self::template(
            'build-content-engine',
            'spreadsheet',
            __('خريطة سؤال إلى محتوى إلى فعل'),
            [
                ['label' => __('محاورك المعلنة'), 'value' => '{content_pillars}'],
                ['label' => __('سؤال العميل'), 'value' => __('سؤال حقيقي سمعته من عميل أو قرأته في تعليق. لا سؤال مفترض.')],
                ['label' => __('شكل الإجابة'), 'value' => __('منشور، أو مقال، أو مقطع، أو رسالة. اختر ما يمكنك أن تنتجه أسبوعيًّا بلا انقطاع.')],
                ['label' => __('أين يُنشر'), 'value' => '{distribution_channels}'],
                ['label' => __('الفعل المطلوب بعد القراءة'), 'value' => __('كل قطعة محتوى تنتهي بفعل واحد. المحتوى الذي لا يطلب شيئًا يجمع إعجابًا لا عميلًا.')],
                ['label' => __('طاقتك الأسبوعية'), 'value' => '{publishing_capacity}'],
                ['label' => __('ما تقيسه'), 'value' => __('مقياس واحد يخصّ الفعل لا الوصول: كم واحدًا نفّذ ما طلبته؟')],
            ],
            [
                __('صفّ واحد لكل سؤال. املأ خمسة صفوف قبل أن تنشر شيئًا — الخريطة قبل الإنتاج.'),
                __('نصف الإنتاج بانتظام أفضل من ضعفه متقطعًا؛ اكتب طاقتك الحقيقية لا المأمولة.'),
            ],
            [
                ['field_key' => 'content_pillars', 'answer_key' => 'answers.content_pillars', 'transform' => 'csv'],
                ['field_key' => 'distribution_channels', 'answer_key' => 'answers.distribution_channels', 'transform' => 'csv'],
                ['field_key' => 'publishing_capacity', 'answer_key' => 'answers.publishing_capacity'],
            ],
        );
    }

    /** @return array<string, mixed> */
    private static function validatePaidCampaign(): array
    {
        return self::template(
            'validate-paid-campaign',
            'checklist',
            __('بطاقة فرضية الحملة'),
            [
                ['label' => __('هدف الحملة'), 'value' => '{campaign_objective}'],
                ['label' => __('الفرضية'), 'value' => __('نعتقد أن [هذا الجمهور] سيستجيب لـ[هذه الرسالة] لأن [هذا السبب]. جملة واحدة قابلة للتكذيب.')],
                ['label' => __('الجمهور'), 'value' => '{audience_definition}'],
                ['label' => __('الوجهة بعد النقر'), 'value' => '{conversion_destination}'],
                ['label' => __('الميزانية والمدة'), 'value' => '{monthly_budget}'],
                ['label' => __('المؤشر ورقمه المستهدف'), 'value' => '{target_metric_value}'],
                ['label' => __('حدّ الإيقاف'), 'value' => __('اكتب الرقم الذي إذا بلغته أوقفت الحملة، واكتبه قبل الإطلاق لا بعده.')],
            ],
            [
                __('الحملة بلا وجهة مهيّأة تحرق الميزانية عند آخر خطوة. افحص الوجهة قبل أن تدفع ريالًا.'),
                __('فرضية لا يمكن أن تَثبُت خطأً ليست فرضية — هي أمنية.'),
            ],
            [
                ['field_key' => 'campaign_objective', 'answer_key' => 'answers.campaign_objective'],
                ['field_key' => 'audience_definition', 'answer_key' => 'answers.audience_definition'],
                ['field_key' => 'conversion_destination', 'answer_key' => 'answers.conversion_destination'],
                ['field_key' => 'monthly_budget', 'answer_key' => 'answers.monthly_budget'],
                ['field_key' => 'target_metric_value', 'answer_key' => 'answers.target_metric_value'],
            ],
        );
    }

    /** @return array<string, mixed> */
    private static function prepareAgencyBrief(): array
    {
        return self::template(
            'prepare-agency-brief',
            'checklist',
            __('قائمة جاهزية موجز الوكالة'),
            [
                ['label' => __('الهدف برقم وتاريخ'), 'value' => '{goal_statement}'],
                ['label' => __('الرقم الذي يُحكم به على النجاح'), 'value' => '{success_number}'],
                ['label' => __('داخل النطاق'), 'value' => '{scope_items}'],
                ['label' => __('خارج النطاق'), 'value' => '{out_of_scope}'],
                ['label' => __('الميزانية'), 'value' => '{budget_range}'],
                ['label' => __('من يملك الحسابات والأصول'), 'value' => '{who_owns_assets}'],
                ['label' => __('إيقاع المراجعة'), 'value' => '{review_rhythm}'],
            ],
            [
                __('«خارج النطاق» أهمّ بند في الورقة: هو ما يمنع الخلاف في الشهر الثالث.'),
                __('ملكية الحسابات تُحسم قبل التوقيع. الحساب الذي يُنشأ باسم الوكالة يبقى معها حين تنتهي العلاقة.'),
            ],
            [
                ['field_key' => 'goal_statement', 'answer_key' => 'answers.goal_statement'],
                ['field_key' => 'success_number', 'answer_key' => 'answers.success_number'],
                ['field_key' => 'scope_items', 'answer_key' => 'answers.scope_items', 'transform' => 'csv'],
                ['field_key' => 'out_of_scope', 'answer_key' => 'answers.out_of_scope', 'transform' => 'csv'],
                ['field_key' => 'budget_range', 'answer_key' => 'answers.budget_range'],
                ['field_key' => 'who_owns_assets', 'answer_key' => 'answers.who_owns_assets'],
                ['field_key' => 'review_rhythm', 'answer_key' => 'answers.review_rhythm'],
            ],
        );
    }

    /** @return array<string, mixed> */
    private static function establishMeasurementBaseline(): array
    {
        return self::template(
            'establish-measurement-baseline',
            'spreadsheet',
            __('جدول خط الأساس الأسبوعي'),
            [
                ['label' => __('نضج القياس عندك اليوم'), 'value' => '{tracking_maturity}'],
                ['label' => __('المؤشر'), 'value' => __('مؤشر واحد يتحرك بما تفعله هذا الشهر. لا تسجّل خمسة لتترك أربعة فارغة بعد أسبوعين.')],
                ['label' => __('من أين يُقرأ الرقم'), 'value' => __('اسم الشاشة أو التقرير بالضبط. مصدران للرقم نفسه يعنيان خلافًا شهريًّا على أيهما الصحيح.')],
                ['label' => __('قيمته اليوم'), 'value' => __('اكتب الرقم الآن قبل أي تغيير. خط الأساس الذي يُسجَّل بعد التنفيذ لا يقيس شيئًا.')],
                ['label' => __('من يسجّله وأي يوم'), 'value' => __('اسم شخص ويوم ثابت من الأسبوع. «نراجعه دوريًّا» تعني أنه لن يُراجَع.')],
                ['label' => __('بعد أربعة أسابيع'), 'value' => __('قارن المتوسطين لا رقمين مفردين — الأسبوع الواحد يتحرك بأسباب لا علاقة لها بعملك.')],
            ],
            [
                __('أربع نقاط زمنية هي أقل ما يُرسَم عليه اتجاه. ما دونها قراءة في ضجيج.'),
                __('سجّل ما حدث في الأسبوع بجانب الرقم: إجازة أو موسم أو عطل يفسّر قفزة لا تفسّرها جهودك.'),
            ],
            [
                ['field_key' => 'tracking_maturity', 'answer_key' => 'answers.tracking_maturity'],
            ],
        );
    }

    /** @return array<string, mixed> */
    private static function improveAiReadiness(): array
    {
        return self::template(
            'improve-ai-readiness',
            'checklist',
            __('بطاقة إصلاح وفحص تقني'),
            [
                ['label' => __('الموقع المفحوص'), 'value' => '{website_url}'],
                ['label' => __('البند المكسور'), 'value' => __('انقله من بطاقة الجاهزية كما هو: بيانات منظّمة، أو فيد منتجات، أو سعر غير مقروء آليًّا، أو صفحة سياسات ناقصة.')],
                ['label' => __('لماذا يهم'), 'value' => __('سطر واحد: ماذا لا يستطيع النموذج أن يعرفه عنك ما دام هذا البند مكسورًا.')],
                ['label' => __('الإصلاح'), 'value' => __('خطوة تقنية واحدة محددة، ومن ينفّذها: أنت أم مطوّر الموقع.')],
                ['label' => __('كيف تتحقق'), 'value' => __('أعد الفحص من بطاقة الجاهزية بعد النشر. البند يتحول من مكسور إلى سليم أو لا يتحول — لا حكم بينهما.')],
                ['label' => __('تاريخ إعادة الفحص'), 'value' => __('حدّد اليوم الآن. النشر بلا إعادة فحص يترك العطل قائمًا وأنت تظنه مُصلحًا.')],
            ],
            [
                __('بند واحد لكل بطاقة. البنود التقنية تُصلَح وتُفحص واحدًا واحدًا وإلا لم تعرف أيها أثّر.'),
                __('هذا المحور يُقاس من موقعك مباشرة لا من وصفك له، فنتيجته تتغير فور نشر الإصلاح.'),
            ],
            [
                ['field_key' => 'website_url', 'answer_key' => 'answers.website_url'],
            ],
        );
    }
}
