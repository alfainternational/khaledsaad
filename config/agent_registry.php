<?php

/*
|--------------------------------------------------------------------------
| سجلّ قدرات الوكلاء (Agent Capability Registry)
|--------------------------------------------------------------------------
|
| المصدر الوحيد للحقيقة لـ«ابنِ شاملاً، اكشف انتقائياً». كل مدخل هنا هو تجسيد
| محلي لوكيل من وكلاء digital-marketing-pro الـ25، يعمل كمهارة (Skill) داخل
| النواة (Brain + SkillRegistry). المستخدم لا يرى «25 وكيلاً» — يرى رحلته
| الأبسط (5 مراحل / 20 أداة / الاستوديو / التقارير) تصبح أذكى خلف الكواليس.
|
| كل قدرة تُكشَف بالمعادلة القائمة: الحالة (status) + الصلاحية (entitlement)
| + مفتاح الميزة (feature_flag) + الشريحة (personas). لا اسم باقة hardcoded.
|
| ملاحظة الكشف: القدرات المكشوفة تعتمد الصلاحية وحدها بوّابةً (feature_flag=null)،
| والباقات تزرع مفاتيح الصلاحيات (PlatformBootstrapSeeder). حالة beta تعني مكشوفة
| لمن يملك الصلاحية مع إمكان الضبط لاحقاً؛ ga مستقرّة؛ hidden مبنية غير مكشوفة بعد.
|
| المفاتيح:
|   name          اسم بشري بالعربية (بصيغة المستخدم لا المطوّر).
|   cluster       العنقود الوظيفي: intelligence|planning|creation|gate|execution|memory.
|   stage         مرحلة المنتج 1..5، أو 0 لقدرة عابرة لكل المراحل.
|   entitlement   مفتاح الصلاحية المطلوب، أو null لقدرة أساسية (core) متاحة دائماً.
|   feature_flag  مفتاح ميزة اختياري للتحكّم التدريجي، أو null.
|   personas      الشرائح المستفيدة (PersonaCatalog). [] = بنية تحتية بلا واجهة مستخدم.
|   wave          موجة التنفيذ: 0 أساس · 1 MVP · 2 V2 · 3 V3.
|   status        دورة حياة الوحدة: hidden|internal|beta|ga (الدستور §16).
|   surface       أين تُحقن في المنصة (أداة/استوديو/تقارير/داشبورد/بنية).
|   summary       سطر واحد: ما الذي يحصل عليه المستخدم فعلاً.
|
*/

return [

    /*
    |----------------------------------------------------------------------
    | 1) طبقة الذكاء والاستشعار
    |----------------------------------------------------------------------
    */
    'market_intelligence' => [
        'name' => 'رصد إشارات السوق',
        'cluster' => 'intelligence',
        'stage' => 0,
        'entitlement' => 'intelligence.market_signals',
        'feature_flag' => null,
        'personas' => ['idea', 'freelancer', 'business', 'team', 'agency'],
        'wave' => 1,
        'status' => 'beta',
        'surface' => 'dashboard+tool:diagnosis',
        'summary' => 'إشارات السوق والتوقيت المناسب لتحرّكك، مستخلصة تلقائياً.',
    ],
    'competitive_intel' => [
        'name' => 'تشريح المنافسين',
        'cluster' => 'intelligence',
        'stage' => 2,
        'entitlement' => 'modules.stage_2',
        'feature_flag' => null,
        'personas' => ['freelancer', 'business', 'team', 'agency'],
        'wave' => 1,
        'status' => 'ga',
        'surface' => 'tool:competitors',
        'summary' => 'فجوات منافسيك في المحتوى والسيو والإعلان والتسعير.',
    ],
    'competitor_intelligence' => [
        'name' => 'مراقبة المنافسين المستمرة',
        'cluster' => 'intelligence',
        'stage' => 2,
        'entitlement' => 'intelligence.monitoring',
        'feature_flag' => null,
        'personas' => ['business', 'team', 'agency'],
        'wave' => 2,
        'status' => 'beta',
        'surface' => 'monitoring',
        'summary' => 'إنذار مبكر عند تغيّر منافسيك: تسعير، إعلانات، محتوى، ترتيب.',
    ],
    'seo_specialist' => [
        'name' => 'الظهور في البحث والذكاء',
        'cluster' => 'intelligence',
        'stage' => 2,
        'entitlement' => 'modules.seo',
        'feature_flag' => null,
        'personas' => ['idea', 'freelancer', 'business', 'team', 'agency'],
        'wave' => 1,
        'status' => 'beta',
        'surface' => 'tool:market',
        'summary' => 'ظهورك في جوجل ومحرّكات الإجابة (SEO + AEO + GEO).',
    ],

    /*
    |----------------------------------------------------------------------
    | 2) التخطيط والقياس
    |----------------------------------------------------------------------
    */
    'marketing_strategist' => [
        'name' => 'الخطوة التالية الأذكى',
        'cluster' => 'planning',
        'stage' => 0,
        'entitlement' => null, // core — يقود الداشبورد للجميع.
        'feature_flag' => null,
        'personas' => ['idea', 'freelancer', 'business', 'team', 'agency'],
        'wave' => 1,
        'status' => 'ga',
        'surface' => 'dashboard:next_step',
        'summary' => 'يعرف أين أنت في رحلتك ويقترح خطوتك التالية الأعلى أثراً.',
    ],
    'analytics_analyst' => [
        'name' => 'قراءة النتائج',
        'cluster' => 'planning',
        'stage' => 5,
        'entitlement' => 'modules.stage_5',
        'feature_flag' => null,
        'personas' => ['business', 'team', 'agency'],
        'wave' => 1,
        'status' => 'ga',
        'surface' => 'reports',
        'summary' => 'مؤشّراتك وتقاريرك مترجَمة إلى قرارات واضحة.',
    ],
    'marketing_scientist' => [
        'name' => 'القياس المتقدّم',
        'cluster' => 'planning',
        'stage' => 5,
        'entitlement' => 'analytics.advanced',
        'feature_flag' => null,
        'personas' => ['team', 'agency'],
        'wave' => 2,
        'status' => 'beta',
        'surface' => 'reports:advanced',
        'summary' => 'أثر كل قناة سببياً (MMM) وسيناريوهات العائد المتوقّع.',
    ],

    /*
    |----------------------------------------------------------------------
    | 3) صنّاع المحتوى والقنوات
    |----------------------------------------------------------------------
    */
    'content_creator' => [
        'name' => 'كاتب المحتوى',
        'cluster' => 'creation',
        'stage' => 4,
        'entitlement' => 'modules.ai_studio',
        'feature_flag' => null,
        'personas' => ['idea', 'freelancer', 'business', 'team', 'agency'],
        'wave' => 1,
        'status' => 'ga',
        'surface' => 'studio',
        'summary' => 'محتوى جاهز على صوت علامتك: مقالات، إعلانات، منشورات.',
    ],
    'social_media_manager' => [
        'name' => 'مدير السوشيال',
        'cluster' => 'creation',
        'stage' => 4,
        'entitlement' => 'modules.ai_studio',
        'feature_flag' => null,
        'personas' => ['idea', 'freelancer', 'business', 'team', 'agency'],
        'wave' => 2,
        'status' => 'beta',
        'surface' => 'studio',
        'summary' => 'خطة محتوى ومنشورات مناسبة لكل منصّة على حدة.',
    ],
    'email_specialist' => [
        'name' => 'خبير الإيميل والمتابعة',
        'cluster' => 'creation',
        'stage' => 4,
        'entitlement' => 'modules.ai_studio',
        'feature_flag' => null,
        'personas' => ['freelancer', 'business', 'team', 'agency'],
        'wave' => 2,
        'status' => 'beta',
        'surface' => 'studio',
        'summary' => 'تسلسلات إيميل ومتابعة تصل وتحوّل.',
    ],
    'media_buyer' => [
        'name' => 'مشتري الإعلانات',
        'cluster' => 'creation',
        'stage' => 4,
        'entitlement' => 'modules.campaigns',
        'feature_flag' => null,
        'personas' => ['business', 'team', 'agency'],
        'wave' => 2,
        'status' => 'beta',
        'surface' => 'tool:campaigns',
        'summary' => 'بنية حملة مدفوعة: جمهور، مزايدة، وتيرة إنفاق.',
    ],
    'cro_specialist' => [
        'name' => 'محسّن العرض والتحويل',
        'cluster' => 'creation',
        'stage' => 3,
        'entitlement' => 'modules.stage_3',
        'feature_flag' => null,
        'personas' => ['idea', 'freelancer', 'business', 'team', 'agency'],
        'wave' => 1,
        'status' => 'ga',
        'surface' => 'tool:offer',
        'summary' => 'عرض أوضح وتسعير أذكى وصفحات تحوّل أكثر.',
    ],
    'growth_engineer' => [
        'name' => 'مهندس النمو',
        'cluster' => 'creation',
        'stage' => 4,
        'entitlement' => 'modules.growth',
        'feature_flag' => null,
        'personas' => ['business', 'team', 'agency'],
        'wave' => 2,
        'status' => 'beta',
        'surface' => 'tool:funnel',
        'summary' => 'حلقات نمو قابلة للتكرار مبنية على اقتصاد الوحدة.',
    ],
    'journey_orchestrator' => [
        'name' => 'مصمّم رحلة العميل',
        'cluster' => 'creation',
        'stage' => 4,
        'entitlement' => 'modules.journeys',
        'feature_flag' => null,
        'personas' => ['business', 'team', 'agency'],
        'wave' => 2,
        'status' => 'beta',
        'surface' => 'tool:journey',
        'summary' => 'رحلة عميل موحّدة عبر القنوات مع نقاط تماس وتوقيت.',
    ],
    'crm_manager' => [
        'name' => 'إدارة الليدات والعملاء',
        'cluster' => 'creation',
        'stage' => 4,
        'entitlement' => 'modules.crm',
        'feature_flag' => null,
        'personas' => ['team', 'agency'],
        'wave' => 3,
        'status' => 'beta',
        'surface' => 'module:crm',
        'summary' => 'ربط الليدات بالحملات وإسناد مغلق الحلقة.',
    ],
    'influencer_manager' => [
        'name' => 'إدارة المؤثّرين',
        'cluster' => 'creation',
        'stage' => 4,
        'entitlement' => 'modules.influencer',
        'feature_flag' => null,
        'personas' => ['business', 'agency'],
        'wave' => 3,
        'status' => 'beta',
        'surface' => 'tool:influencer',
        'summary' => 'اختيار مؤثّرين وموجز حملة والتزام إفصاح.',
    ],
    'pr_outreach' => [
        'name' => 'العلاقات العامة والسلطة',
        'cluster' => 'creation',
        'stage' => 4,
        'entitlement' => 'modules.pr',
        'feature_flag' => null,
        'personas' => ['business', 'team', 'agency'],
        'wave' => 3,
        'status' => 'beta',
        'surface' => 'tool:pr',
        'summary' => 'إعلام مكتسب وبناء سلطة ومصداقية للعلامة.',
    ],
    'localization_specialist' => [
        'name' => 'التعريب والصياغة',
        'cluster' => 'creation',
        'stage' => 0,
        'entitlement' => null, // core — العربية/RTL جوهر المنصّة لا إضافة.
        'feature_flag' => null,
        'personas' => ['idea', 'freelancer', 'business', 'team', 'agency'],
        'wave' => 1,
        'status' => 'ga',
        'surface' => 'infra:i18n',
        'summary' => 'كل مخرَج بعربية سليمة تحترم السياق الثقافي وRTL.',
    ],

    /*
    |----------------------------------------------------------------------
    | 4) بوابة الجودة والامتثال (بنية عابرة)
    |----------------------------------------------------------------------
    */
    'brand_guardian' => [
        'name' => 'حارس العلامة',
        'cluster' => 'gate',
        'stage' => 0,
        'entitlement' => null, // core — بوابة إلزامية قبل كل تسليم.
        'feature_flag' => null,
        'personas' => ['idea', 'freelancer', 'business', 'team', 'agency'],
        'wave' => 0,
        'status' => 'ga',
        'surface' => 'infra:gate',
        'summary' => 'يمنع مرور محتوى ضعيف أو مخالف قبل أن تراه.',
    ],
    'quality_assurance' => [
        'name' => 'ضمان الجودة',
        'cluster' => 'gate',
        'stage' => 0,
        'entitlement' => null, // core.
        'feature_flag' => null,
        'personas' => ['idea', 'freelancer', 'business', 'team', 'agency'],
        'wave' => 0,
        'status' => 'ga',
        'surface' => 'infra:gate',
        'summary' => 'يقيس جودة كل مخرَج ويقترح إصلاحاً قبل النشر.',
    ],

    /*
    |----------------------------------------------------------------------
    | 5) التنفيذ والعمليات
    |----------------------------------------------------------------------
    */
    'execution_coordinator' => [
        'name' => 'منسّق النشر والموافقات',
        'cluster' => 'execution',
        'stage' => 0,
        'entitlement' => 'execution.publish',
        'feature_flag' => null,
        'personas' => ['business', 'team', 'agency'],
        'wave' => 2,
        'status' => 'beta',
        'surface' => 'infra:approvals',
        'summary' => 'نشر خارجي بموافقة بشرية وسقف ميزانية وسجل تدقيق.',
    ],
    'performance_monitor' => [
        'name' => 'مراقب الأداء',
        'cluster' => 'execution',
        'stage' => 5,
        'entitlement' => 'monitoring',
        'feature_flag' => null,
        'personas' => ['business', 'team', 'agency'],
        'wave' => 2,
        'status' => 'beta',
        'surface' => 'monitoring',
        'summary' => 'رصد لحظي للأداء وإنذار عند الشذوذ أو تجاوز الميزانية.',
    ],
    'agency_operations' => [
        'name' => 'عمليات الوكالة',
        'cluster' => 'execution',
        'stage' => 0,
        'entitlement' => 'modules.agency_mode',
        'feature_flag' => null,
        'personas' => ['agency'],
        'wave' => 3,
        'status' => 'beta',
        'surface' => 'agency',
        'summary' => 'محفظة عملاء وتقارير بيضاء وموافقات في مكان واحد.',
    ],

    /*
    |----------------------------------------------------------------------
    | 6) التعلّم والذاكرة (بنية تحتية — لا واجهة مستخدم)
    |----------------------------------------------------------------------
    */
    'intelligence_curator' => [
        'name' => 'عمود التعلّم',
        'cluster' => 'memory',
        'stage' => 0,
        'entitlement' => null,
        'feature_flag' => null,
        'personas' => [], // بنية تحتية.
        'wave' => 0,
        'status' => 'ga',
        'surface' => 'infra:knowledge',
        'summary' => 'يجمع تعلّم كل نشاط ويعيده سياقاً جاهزاً لأي مهارة.',
    ],
    'memory_manager' => [
        'name' => 'مدير الذاكرة',
        'cluster' => 'memory',
        'stage' => 0,
        'entitlement' => null,
        'feature_flag' => null,
        'personas' => [], // بنية تحتية.
        'wave' => 0,
        'status' => 'ga',
        'surface' => 'infra:memory',
        'summary' => 'يخزّن ويفهرس ويسترجع كل معرفة بلا تكرار.',
    ],

];
