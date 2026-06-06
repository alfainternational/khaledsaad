<?php

namespace App\Support;

class PlatformSectionCatalog
{
    /**
     * @return array<string, mixed>
     */
    public static function home(): array
    {
        return [
            'title' => 'Marketing Intelligence Platform',
            'description' => 'منصة عربية تحلل موقعك وحساباتك ومنافسيك وتحوّل ذلك إلى درجات صريحة وتشخيص صادق وخطة تنفيذ مرتبة.',
            'hero' => [
                'eyebrow' => 'Website Audit + Social Audit + Competitor Intelligence',
                'headline' => 'اقرأ وضعك التسويقي بصدق، وحوّله إلى أولويات تنفيذ واضحة.',
                'body' => 'أدخل موقعك وروابطك الرسمية والمنافسين، وستحصل على درجات عملية، فجوات واضحة، official lead intelligence، ومخرجات جاهزة للتنفيذ داخل نفس المنصة.',
                'primaryCta' => ['label' => 'ابدأ تحليل مشروعك', 'href' => route('register')],
                'secondaryCta' => ['label' => 'استعرض طبقات المنصة', 'href' => route('tools.index')],
            ],
            'metrics' => [
                ['value' => '9', 'label' => 'درجات readiness مترابطة'],
                ['value' => '5', 'label' => 'مخارج تنفيذ فورية'],
                ['value' => '1', 'label' => 'تقرير موحد للموقع والسوشيال والمنافسة'],
            ],
            'benefits' => [
                ['title' => 'تشخيص صادق', 'body' => 'تعرف أين الخلل فعلاً: في الوضوح، الثقة، الرسائل، الـ SEO، التحويل، أو الحضور الاجتماعي.'],
                ['title' => 'مقارنة منافسين قابلة للفعل', 'body' => 'ترى من الأكثر وضوحاً ونشاطاً وإقناعاً، وأين توجد فرصة التميز الفعلية لك.'],
                ['title' => 'خطة تنفيذ مبنية على evidence', 'body' => 'بدلاً من توصيات عامة، تحصل على quick wins وتحسينات 30 و90 يوماً مرتبطة بالأثر التجاري.'],
            ],
            'audiences' => [
            ['name' => 'أصحاب المشاريع', 'summary' => 'لمن يريد معرفة ما الذي يمنع النمو فعلاً قبل صرف الجهد على قنوات أو حملات خاطئة.', 'href' => route('paths.index')],
            ['name' => 'مقدمو الخدمات', 'summary' => 'لمن يريد تشخيص العميل المحتمل بسرعة، ثم تحويل النتيجة إلى عرض وخطة ومخرجات تنفيذية.', 'href' => route('paths.index')],
            ['name' => 'الفرق الداخلية', 'summary' => 'لمن يريد executive summary للقرار وتفصيل عملي للمسوق والمطور داخل تجربة واحدة.', 'href' => route('paths.index')],
            ['name' => 'الوكالات', 'summary' => 'لمن يريد Marketing Intelligence قابلة للتكرار على عدة مشاريع وعملاء مع مقارنة ومراقبة دورية.', 'href' => route('paths.index')],
        ],
        'studioProgressItems' => [
            ['label' => 'استكمال بيانات المشروع', 'percentage' => 100, 'pctClass' => 'text-success', 'fillClass' => 'studio-progress-fill-success'],
            ['label' => 'تحليل السياق التسويقي', 'percentage' => 85, 'pctClass' => 'text-info', 'fillClass' => 'studio-progress-fill-info'],
            ['label' => 'صياغة المخرجات', 'percentage' => 60, 'pctClass' => 'text-accent', 'fillClass' => 'studio-progress-fill-accent'],
        ],
            'heroAvatarToneClasses' => [
                'hero-avatar-tone-primary',
                'hero-avatar-tone-teal',
                'hero-avatar-tone-gold',
                'hero-avatar-tone-rose',
                'hero-avatar-tone-violet',
            ],
            'heroAvatarDepthClasses' => [
                'hero-avatar-depth-1',
                'hero-avatar-depth-2',
                'hero-avatar-depth-3',
                'hero-avatar-depth-4',
                'hero-avatar-depth-5',
            ],
            'metricToneClasses' => [
                'metric-top-bar-primary',
                'metric-top-bar-teal',
                'metric-top-bar-gold',
            ],
            'benefitToneClasses' => [
                'benefit-tone-primary',
                'benefit-tone-teal',
                'benefit-tone-gold',
                'benefit-tone-rose',
            ],
    ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function section(string $page): array
    {
        return static::sections()[$page] ?? abort(404);
    }

    /**
     * @return array<string, mixed>
     */
    public static function paths(): array
    {
        return [
            ...static::section('paths'),
            'pathCards' => static::pathCards(),
            'pathPrinciples' => static::pathPrinciples(),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function sections(): array
    {
        return [
            'paths' => [
                'title' => 'المسارات',
                'description' => 'اختر السياق الأقرب لنشاطك، ثم دع النظام يكيّف scoring والتوصيات والتحسينات بحسب القطاع والجاهزية الحالية.',
                'eyebrow' => 'ابدأ من وضعك الحقيقي',
                'goal' => 'الوصول إلى تحليل يناسب قطاعك بدلاً من تقرير عام لا يفرّق بين متجر وعيادة وشركة خدمات.',
                'nextStep' => 'اختر مسارك أو قطاعك، ثم ابدأ إدخال الموقع والحسابات والمنافسين للحصول على baseline واضح.',
                'highlights' => [
                    ['title' => 'قوالب قطاعية', 'body' => 'المتجر والعيادة والمطعم وSaaS لا يجب أن يُقاسوا بنفس المعايير أو بنفس الأولويات.'],
                    ['title' => 'تحليل من أول خطوة', 'body' => 'المسار يبدأ بإدخال الأصول الرسمية لا بملء أسئلة عامة منفصلة عن الواقع.'],
                    ['title' => 'كل اختيار ينعكس على التقييم', 'body' => 'القطاع والسوق والأهداف يغيّرون تفسير النتائج والأولويات المقترحة لاحقاً.'],
                ],
                'actions' => [
                    ['label' => 'ابدأ التحليل', 'href' => route('projects.index')],
                    ['label' => 'استعرض طبقات المنصة', 'href' => route('tools.index')],
                ],
            ],
            'tools' => [
                'title' => 'طبقات التحليل',
                'description' => 'المنصة تبدأ من Intelligence Layer: Website Audit, Social Audit, Competitor Analysis, Messaging Honesty, SEO, Conversion, Ads, AI Visibility, Lead Intelligence.',
                'eyebrow' => 'التحليل قبل التنفيذ',
                'goal' => 'معرفة ما الذي يجب إصلاحه ولماذا قبل فتح الاستوديو أو تشغيل أدوات التنفيذ.',
                'nextStep' => 'ابدأ بالتدقيق الأساسي، ثم افتح Action Workspace لتنفيذ الأولويات المستخرجة من التقرير نفسه.',
                'highlights' => [
                    ['title' => 'درجات مترابطة', 'body' => 'كل finding يؤثر على scorecards واضحة: Website, Social, SEO, Trust, Conversion, Ads, AI Visibility, Competition, Lead Readiness.'],
                    ['title' => 'تشخيص لا يجمّل الحقيقة', 'body' => 'النظام يلتقط الرسائل العامة والـ CTA الضعيف والتناقضات والثقة الناقصة بدل الاكتفاء بمظهر جميل.'],
                    ['title' => 'الأثر التجاري أولاً', 'body' => 'التوصيات لا تُرتب حسب الشكل فقط، بل حسب تأثيرها على التحويل والظهور والجاهزية للإعلان والنمو.'],
                ],
                'actions' => [
                    ['label' => 'افتح Action Workspace', 'href' => route('studio.index')],
                    ['label' => 'راجع التقارير', 'href' => route('reports.index')],
                ],
            ],
            'studio' => [
                'title' => 'Action Workspace',
                'description' => 'الاستوديو الآن يأتي بعد التقرير، ويستخدم findings والدرجات وجهات الاتصال الرسمية لتوليد التنفيذ المناسب لا مخرجات عامة.',
                'eyebrow' => 'نفّذ بناءً على الواقع',
                'goal' => 'تحويل التشخيص إلى محتوى، رسائل، صفحات، وخطوات تنفيذ ترتبط بالمشكلات المكتشفة فعلياً.',
                'nextStep' => 'بعد ظهور التقرير، استخدم الاستوديو لمعالجة الأولويات الأعلى بدلاً من البدء من صفحة بيضاء.',
                'highlights' => [
                    ['title' => 'يعرف نتائج التدقيق أولاً', 'body' => 'الاستوديو يقرأ brief المشروع مع scorecards وhonest diagnosis قبل اقتراح أي مخرج.'],
                    ['title' => 'يبني على evidence', 'body' => 'صفحات الخدمات والرسائل والعروض المقترحة تنطلق من مشاكل موثقة لا من افتراضات عامة.'],
                    ['title' => 'يرتبط بالفرصة التالية', 'body' => 'كل تشغيل داخل Action Workspace يجب أن يخدم priority action واضحة من التقرير.'],
                ],
                'actions' => [
                    ['label' => 'استعرض القوالب', 'href' => route('templates.index')],
                    ['label' => 'اعرض طبقات التحليل', 'href' => route('tools.index')],
                ],
            ],
            'templates' => [
                'title' => 'القوالب',
                'description' => 'القوالب تمنحك بداية عملية سريعة عندما تعرف ما تريد ولا تريد البدء من الصفر.',
                'eyebrow' => 'ابدأ من نقطة أقوى',
                'goal' => 'تسريع التنفيذ مع الحفاظ على صياغة واضحة ورسالة متسقة.',
                'nextStep' => 'اختر القالب الأقرب لما تحتاجه، ثم خصصه بما يناسب مشروعك أو مرّره إلى الاستوديو الذكي لتطويره.',
                'highlights' => [
                    ['title' => 'ليست نصوصاً عامة', 'body' => 'كل قالب مصمم ليخدم استخداماً واضحاً وموقفاً عملياً محدداً.'],
                    ['title' => 'تتعدل بسهولة', 'body' => 'ابدأ بسرعة، ثم اجعل الناتج أقرب إلى صوت مشروعك ورسالتك.'],
                    ['title' => 'تختصر وقت البداية', 'body' => 'تمنحك نقطة انطلاق أسرع حين تكون السرعة مهمة.'],
                ],
                'actions' => [
                    ['label' => 'افتح الاستوديو الذكي', 'href' => route('studio.index')],
                    ['label' => 'ابدأ من المسارات', 'href' => route('paths.index')],
                ],
            ],
            'reports' => [
                'title' => 'التقارير',
                'description' => 'التقارير أصبحت لوحة Intelligence: درجات تنفيذية، تشخيص صادق، مقارنة منافسين، official contacts، واتجاهات before/after ومراقبة دورية.',
                'eyebrow' => 'Executive view + operational detail',
                'goal' => 'فهم الوضع الحالي والتغيّر مع الوقت، ومعرفة أين يجب أن يتحرك الفريق الآن.',
                'nextStep' => 'راجع scorecards والفجوات، ثم ادفع الأولويات الأعلى إلى Action Workspace أو دورة تدقيق جديدة.',
                'highlights' => [
                    ['title' => 'قبل/بعد واضح', 'body' => 'تستطيع مقارنة التحسن في السرعة، SEO، الثقة، والتحويل عبر snapshots دورية محفوظة.'],
                    ['title' => 'قراءة تنفيذية وتشغيلية معاً', 'body' => 'صاحب النشاط يرى executive score، والفريق يرى findings والتوصيات العملية نفسها.'],
                    ['title' => 'المنصة تلتقط التراجع', 'body' => 'عند المراقبة الدورية تظهر الانخفاضات والتوقفات بدل انتظار اكتشافها متأخراً.'],
                ],
                'actions' => [
                    ['label' => 'اعرض المشاريع', 'href' => route('projects.index')],
                    ['label' => 'اذهب إلى طبقات التحليل', 'href' => route('tools.index')],
                ],
            ],
            'projects' => [
                'title' => 'المشاريع',
                'description' => 'هنا يصبح كل مشروع أوضح: ماذا أُنجز فيه، ما الذي ينقصه، وأين توجد مخرجاته وخطواته التالية.',
                'eyebrow' => 'مشاريعك في مكان واحد',
                'goal' => 'تنظيم العمل حتى لا تضيع النتائج والخطوات بين ملفات ومساحات متفرقة.',
                'nextStep' => 'ادخل مشروعك الحالي، ثم انتقل مباشرة إلى المرحلة أو الأداة الأنسب.',
                'highlights' => [
                    ['title' => 'نتائجك لا تضيع', 'body' => 'كل ما تنجزه يبقى محفوظاً داخل المشروع الذي يخصه.'],
                    ['title' => 'تتابع التقدم بسهولة', 'body' => 'تعرف أين يقف المشروع الآن وما الذي يجب فعله بعده.'],
                    ['title' => 'يخدم الفرد والفريق', 'body' => 'سواء كنت تعمل وحدك أو مع فريق، يظل العمل منظماً وواضحاً.'],
                ],
                'actions' => [
                    ['label' => 'استعرض وضع الوكالة', 'href' => route('agency.index')],
                    ['label' => 'إعدادات الحساب', 'href' => route('account.index')],
                ],
            ],
            'account' => [
                'title' => 'الحساب والإعدادات',
                'description' => 'هذه الصفحة تمنحك تحكماً أوضح في إعداداتك، وتفضيلاتك، ووصولك إلى بيئات العمل المختلفة داخل المنصة.',
                'eyebrow' => 'تجربة تناسبك',
                'goal' => 'إدارة إعدادات حسابك بسهولة مع انتقال واضح بين العمل والإعدادات.',
                'nextStep' => 'اضبط ما تحتاجه هنا، ثم عد مباشرة إلى مشروعك أو مساحة العمل التي تعمل عليها.',
                'highlights' => [
                    ['title' => 'كل إعداداتك في مكان واحد', 'body' => 'اللغة والتفضيلات والوصول تبقى سهلة المراجعة والتحديث.'],
                    ['title' => 'تنقل أسهل', 'body' => 'إذا كنت تعمل في أكثر من مساحة أو دور، يبقى الانتقال منظماً وواضحاً.'],
                    ['title' => 'تجربة أكثر راحة', 'body' => 'الهدف أن تشعر أن الحساب يخدم عملك، لا أنه طبقة إضافية معقدة.'],
                ],
                'actions' => [
                    ['label' => 'اذهب إلى المشاريع', 'href' => route('projects.index')],
                    ['label' => 'اعرض لوحة الإدارة', 'href' => route('admin.dashboard')],
                ],
            ],
            'agency' => [
                'title' => 'وضع الوكالة',
                'description' => 'مناسب للوكالات التي تريد إدارة أكثر من عميل بشكل منظم، مع وضوح بين المشاريع والمخرجات والسياق الخاص بكل عميل.',
                'eyebrow' => 'إذا كنت تدير عدة عملاء',
                'goal' => 'مساعدتك على تنظيم العمل مع العملاء داخل تجربة واحدة أكثر وضوحاً وقابلية للتوسع.',
                'nextStep' => 'رتب العملاء والمشاريع أولاً، ثم وسّع التعاون والمتابعة حسب الحاجة.',
                'highlights' => [
                    ['title' => 'كل عميل في سياقه', 'body' => 'يبقى لكل عميل مشاريعه ومخرجاته ومساره الواضح داخل المنصة.'],
                    ['title' => 'تنظيم أفضل للعمل', 'body' => 'كلما زاد عدد العملاء، زادت أهمية الوضوح والعزل بين المشاريع.'],
                    ['title' => 'يتوسع مع نموك', 'body' => 'التجربة مصممة لتخدم الوكالة مع زيادة العملاء والمشاريع.'],
                ],
                'actions' => [
                    ['label' => 'اذهب إلى المشاريع', 'href' => route('projects.index')],
                    ['label' => 'اعرض لوحة الإدارة', 'href' => route('admin.dashboard')],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    public static function pathCards(): array
    {
        return [
            ['name' => 'مسار البداية', 'audience' => 'لمن لديه فكرة أو مشروع في مراحله الأولى', 'problem' => 'تحتاج إلى وضوح: هل الفكرة قابلة للبناء؟ من العميل؟ وما الرسالة الأنسب؟', 'promise' => 'يمنحك هذا المسار نقطة بداية عملية تساعدك على فهم مشروعك وصياغة أساسه التسويقي.'],
            ['name' => 'مسار مقدم الخدمة', 'audience' => 'لمن يقدّم خدمة ويحتاج إلى عرض أقوى ورسائل أوضح', 'problem' => 'تملك خبرة أو خدمة، لكن عرضك الحالي لا يظهر القيمة كما يجب.', 'promise' => 'يساعدك على إعادة صياغة العرض والتسعير والرسائل التسويقية بطريقة أكثر إقناعاً.'],
            ['name' => 'مسار المشروع القائم', 'audience' => 'للمشاريع التي تعمل بالفعل وتريد نمواً أوضح', 'problem' => 'هناك جهد موجود، لكن المحتوى أو الحملات أو الرسائل أو التحويل تحتاج إلى ترتيب وتحسين.', 'promise' => 'يركز هذا المسار على تحسين ما لديك بالفعل وبناء خطوات نمو أكثر اتساقاً.'],
            ['name' => 'مسار الشركة أو الفريق', 'audience' => 'لمن يدير فريقاً ويحتاج إلى وضوح في التنفيذ والمتابعة', 'problem' => 'الأفكار كثيرة والتنفيذ موزع، لكن لا توجد صورة موحدة واضحة لما يجب فعله ومتى.', 'promise' => 'يساعد هذا المسار على توحيد الرؤية وربط الجهود اليومية بخطة تسويقية عملية.'],
            ['name' => 'المسار الاحترافي', 'audience' => 'لمن يريد عمقاً أكبر وتحكماً أوسع في القرارات', 'problem' => 'تحتاج إلى مستوى أعمق من التحليل وصياغة العرض والرسائل والمخرجات.', 'promise' => 'يفتح لك هذا المسار تجربة أكثر عمقاً وتفصيلاً لتطوير القرار والتنفيذ بشكل احترافي.'],
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    public static function pathPrinciples(): array
    {
        return [
            ['title' => 'لا تبدأ من كل شيء', 'body' => 'المسار الصحيح يختصر عليك التشتت ويمنحك نقطة بداية منطقية.'],
            ['title' => 'كل مسار له هدف واضح', 'body' => 'المنصة لا تعرض لك نفس التجربة مهما كان وضعك، بل تقرّب لك ما تحتاجه فعلاً.'],
            ['title' => 'الهدف أن تصل لأول نتيجة مفيدة بسرعة', 'body' => 'المسار الجيد لا يطيل عليك الطريق، بل يضعك على الخطوة الأولى الصحيحة.'],
        ];
    }
}
